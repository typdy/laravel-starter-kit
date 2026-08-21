<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Typdy\StarterKit\Laravel\Support\Sync\Data\IncludeDiscoveryData;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Contracts\Relatable;

use function array_filter;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function method_exists;

final readonly class IncludeDiscovery
{
    public function __construct(
        private int $maxDepth = 3,
    ) {}

    /**
     * @param array<int, Construct&Model> $models
     */
    public function discover(array $models): IncludeDiscoveryData
    {
        $includes = [];
        $deferredIncludes = [];

        foreach ($models as $model) {
            $blueprint = $model->getBlueprint();

            [$withinDepth, $deferred] = $this->discoverForModel(
                model: $model,
                ancestry: [$blueprint],
                prefix: null,
                depth: 1,
            );

            $includes[$blueprint] = $this->normalizeIncludes($withinDepth);
            $deferredIncludes[$blueprint] = $this->normalizeIncludes($deferred);
        }

        return new IncludeDiscoveryData($includes, $deferredIncludes);
    }

    /**
     * @param list<string> $ancestry
     *
     * @return list{list<string>, list<string>}
     */
    private function discoverForModel(Construct&Model $model, array $ancestry, ?string $prefix, int $depth): array
    {
        $withinDepth = [];
        $deferred = [];

        if (!$model instanceof Relatable) {
            return [$withinDepth, $deferred];
        }

        /**
         * @var array<string, string> $relationships
         *
         * @mago-expect analysis:non-documented-method uncamelFields is defined in HasRelationships trait
         */
        $relationships = $model->uncamelFields($model->getRelationships());

        foreach ($relationships as $name => $method) {
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

            $path = $prefix !== null ? $prefix . '.' . $name : $name;

            if ($depth <= $this->maxDepth) {
                $withinDepth[] = $path;
            } else {
                $deferred[] = $path;

                continue;
            }

            $relatedBlueprint = $related->getBlueprint();

            if (in_array($relatedBlueprint, $ancestry, strict: true)) {
                continue;
            }

            [$nextWithinDepth, $nextDeferred] = $this->discoverForModel(
                model: $related,
                ancestry: [...$ancestry, $relatedBlueprint],
                prefix: $path,
                depth: $depth + 1,
            );

            if (count($nextWithinDepth) > 0) {
                $withinDepth = [...$withinDepth, ...$nextWithinDepth];
            }

            if (count($nextDeferred) > 0) {
                $deferred = [...$deferred, ...$nextDeferred];
            }
        }

        return [$withinDepth, $deferred];
    }

    /**
     * @param list<string|null> $includes
     *
     * @return list<string>
     */
    private function normalizeIncludes(array $includes): array
    {
        return array_filter($includes) |> array_unique(...) |> array_values(...);
    }
}
