<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Typdy\StarterKit\Laravel\Support\Sync\Data\FetchPlanData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\FetchTaskData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\IncludeDiscoveryData;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Contracts\Relatable;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Utils\Arr;

use function array_diff;
use function array_filter;
use function array_map;
use function array_shift;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function method_exists;
use function sort;

final readonly class FetchPlanner
{
    /**
     * @param list<Construct&Model> $models
     * @param list<Collection> $repos
     */
    public function build(array $models, IncludeDiscoveryData $includes, array $repos): FetchPlanData
    {
        $global = new FetchTaskData(blueprint: 'global', path: 'globals');

        // we only want to fetch constructs that have a repository
        $blueprints = array_map(
            static fn (Collection $repo): string => $repo->getBlueprint(),
            array_filter($repos, static fn (Collection $repo): bool => !$repo->isGlobal()),
        )
            |> array_unique(...)
            |> array_values(...);

        $initial = $this->orderTasks($this->makeFetchTasks($blueprints, $includes->blueprintPaths), $models);

        $deferred = $this->makeFetchTasks($blueprints, $includes->deferredBlueprintPaths);

        return new FetchPlanData($global, $initial, $deferred);
    }

    /**
     * @param list<Construct&Model> $models
     *
     * @return list<Construct&Model>
     */
    private function filterModelsByBlueprint(array $models, string $blueprint): array
    {
        return array_filter(
            $models,
            static fn (Construct $model): bool => $model->getBlueprint() === $blueprint,
        )
            |> array_values(...);
    }

    /**
     * @param list<FetchTaskData> $tasks
     * @param string|list<string> $blueprint
     *
     * @return list<FetchTaskData>
     */
    private function filterTasksByBlueprint(array $tasks, string|array $blueprint): array
    {
        $blueprints = Arr::wrap($blueprint);

        uasort(
            $tasks,
            static fn (FetchTaskData $a, FetchTaskData $b): int => $a->blueprint <=> $b->blueprint,
        );

        return array_filter(
            $tasks,
            static fn (FetchTaskData $task): bool => in_array($task->blueprint, $blueprints, strict: true),
        )
            |> array_values(...);
    }

    /**
     * @param list<FetchTaskData> $tasks
     *
     * @return list<string>
     */
    private function getBlueprintsFromTasks(array $tasks): array
    {
        return array_map(
            static fn (FetchTaskData $task): string => $task->blueprint,
            $tasks,
        )
            |> array_filter(...)
            |> array_unique(...)
            |> array_values(...);
    }

    /**
     * @param list<string> $blueprints
     * @param array<string, list<string>> $includes
     *
     * @return list<FetchTaskData>
     */
    private function makeFetchTasks(array $blueprints, array $includes): array
    {
        $tasks = [];

        foreach ($blueprints as $blueprint) {
            $parameters = [];

            $include = $includes[$blueprint] ?? [];

            if ($include !== []) {
                $parameters['include'] = implode(',', $include);
            }

            $tasks[] = new FetchTaskData(
                blueprint: $blueprint,
                path: 'constructs/' . $blueprint,
                parameters: $parameters,
            );
        }

        return $tasks;
    }

    /**
     * @param list<FetchTaskData> $tasks
     * @param list<Construct&Model> $models
     *
     * @return list<FetchTaskData>
     */
    private function orderTasks(array $tasks, array $models): array
    {
        $deps = [];
        $depCounts = [];

        foreach ($tasks as $task) {
            $blueprint = $task->blueprint;

            $blueprintModels = $this->filterModelsByBlueprint($models, $blueprint);

            $deps[$blueprint] ??= [];
            $depCounts[$blueprint] ??= 0;

            foreach ($blueprintModels as $model) {
                $relatedBlueprints = $this->resolveRelatedBlueprints($model);

                foreach ($relatedBlueprints as $target) {
                    if ($target === $blueprint) {
                        continue;
                    }

                    // @mago-expect analysis:possibly-undefined-string-array-index set above
                    // @mago-expect analysis:possibly-null-argument set above
                    if (in_array($target, $deps[$blueprint], strict: true)) {
                        continue;
                    }

                    $deps[$blueprint][] = $target;
                    $depCounts[$target] ??= 0;
                    $depCounts[$target]++;
                }
            }
        }

        // queue blueprints with no dependencies
        $queue = array_filter(
            $this->getBlueprintsFromTasks($tasks),
            static fn (string $blueprint): bool => (int) ($depCounts[$blueprint] ?? 0) === 0,
        );

        sort($queue);

        $ordered = [];

        while ($queue !== []) {
            $blueprint = array_shift($queue);

            $ordered = [
                ...$ordered,
                ...$this->filterTasksByBlueprint($tasks, $blueprint),
            ];

            $targets = $deps[$blueprint] ?? [];

            foreach ($targets as $target) {
                $depCounts[$target] ??= 0;
                $depCounts[$target]--;

                // feed the queue ᗧ··•······ᗣ
                if ($depCounts[$target] === 0) {
                    $queue[] = $target;
                }
            }

            sort($queue);
        }

        if (count($ordered) === count($tasks)) {
            return $ordered;
        }

        // we don' really case about the order of the remaining tasks
        $remaining = array_diff(
            $this->getBlueprintsFromTasks($tasks),
            $this->getBlueprintsFromTasks($ordered),
        );

        sort($remaining);

        $remaining = $this->filterTasksByBlueprint($tasks, $remaining);

        return [...$ordered, ...$remaining];
    }

    /**
     * @return list<string>
     */
    private function resolveRelatedBlueprints(Construct&Model $model): array
    {
        $resolved = [];

        if (!$model instanceof Relatable) {
            return $resolved;
        }

        $relationships = $model->getRelationships();

        foreach ($relationships as $method) {
            if (!method_exists($model, $method)) {
                continue;
            }

            // @mago-expect analysis:string-member-selector
            // @mago-expect analysis:mixed-assignment checked below
            $relation = $model->{$method}();

            if (!$relation instanceof Relation) {
                continue;
            }

            $related = $relation->getRelated();

            if (!$related instanceof Construct) {
                continue;
            }

            $resolved[] = $related->getBlueprint();
        }

        return array_unique($resolved) |> array_values(...);
    }
}
