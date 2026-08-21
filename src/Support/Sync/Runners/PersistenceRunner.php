<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Runners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Prompts\Progress;
use RuntimeException;
use Typdy\StarterKit\Laravel\Support\Sync\Concerns\HasTelemetry;
use Typdy\StarterKit\Laravel\Support\Sync\Data\ContentData;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Contracts\Relatable;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Utils\Str;

use function array_key_exists;
use function array_keys;
use function array_values;
use function explode;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;
use function method_exists;
use function property_exists;
use function str_contains;

final class PersistenceRunner
{
    use HasTelemetry;

    /**
     * @param list<Construct&Model> $models
     */
    public function run(ContentData $content, array $models): void
    {
        $persisted = $this->persistResources($content, $models);

        $this->syncRelationships($content, $persisted);
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
     * @param list<Construct&Model> $models
     *
     * @return array<string, array<string, Construct&Model>>
     */
    private function persistResources(ContentData $content, array $models): array
    {
        $persisted = [];

        foreach ($content->resourcesByType as $blueprint => $resources) {
            if ($resources === []) {
                continue;
            }

            $prototype = array_first($this->filterModelsByBlueprint($models, $blueprint));

            if ($prototype === null) {
                throw new RuntimeException("No model found for blueprint '{$blueprint}'");
            }

            $this->startTimer('resources');

            progress(
                label: "Persisting {$blueprint} constructs...",
                steps: $resources,
                callback: function (Resource $resource, Progress $progress) use (
                    $blueprint,
                    $prototype,
                    &$persisted,
                ): void {
                    $model = $prototype::query()->find($resource->id) ?? clone $prototype;

                    $model->hydrateFromResource($resource);
                    $model->save();

                    $name = (string) $model->getKey();

                    // @mago-expect analysis:non-existent-property it does
                    // @mago-expect analysis:non-existent-property it does
                    foreach (['title', 'identifier'] as $prop) {
                        if (!property_exists($model, $prop) || !is_scalar($model->$prop)) {
                            continue;
                        }

                        $name = (string) $model->$prop;
                        break;
                    }

                    $eta = $this->getCountUpEstimate($progress->progress, $progress->total, 'resources');

                    if ($eta !== null) {
                        $eta = " | ETA: {$eta}";
                    }

                    $progress->hint("Persisted {$blueprint} construct: {$name}{$eta}");

                    $persisted[$blueprint][$resource->id] = $model;
                },
            );
        }

        return $persisted;
    }

    /**
     * @param array<string, array<string, Construct&Model>> $persisted
     *
     * @mago-ignore analysis:all we trust the $persisted param and checks here
     *  are sufficient, but mago does not understand them
     */
    private function resolvePersistedFromKey(string $key, array $persisted): Construct&Model
    {
        if (!str_contains($key, ':')) {
            throw new RuntimeException("Invalid key format: {$key}");
        }

        [$type, $id] = explode(':', $key, limit: 2);

        if (($persisted[$type][$id] ?? null) === null) {
            throw new RuntimeException("No persisted model found for key: {$key}");
        }

        return $persisted[$type][$id];
    }

    /**
     * @param array<string, array<string, Construct&Model>> $persisted
     *
     * @mago-ignore analysis:all mago produces a lot of false positives due to
     *  array key conversion in callbacks
     */
    private function syncRelationships(ContentData $content, array $persisted): void
    {
        $toMany = [];
        $toOne = [];

        spin(
            callback: function () use ($content, $persisted, &$toMany, &$toOne): void {
                foreach ($content->relationships as $reference) {
                    $source = $this->resolvePersistedFromKey($reference->sourceKey, $persisted);
                    $target = $this->resolvePersistedFromKey($reference->targetKey, $persisted);

                    if (!$source instanceof Relatable) {
                        continue;
                    }

                    /**
                     * @var array<string, string> $relationships
                     */
                    $relationships = $source->getRelationships();

                    $method = $relationships[$reference->relationship] ?? null;

                    if ($method === null || !method_exists($source, $method)) {
                        continue;
                    }

                    $relation = $source->{$method}();

                    if ($relation instanceof BelongsToMany) {
                        $toMany[$reference->sourceKey][$method] ??= [];
                        $toMany[$reference->sourceKey][$method][$target->getKey()] = (int) $target->getKey();

                        continue;
                    }

                    if ($relation instanceof BelongsTo) {
                        $toOne[$reference->sourceKey][$method] = $target;
                    }
                }

                foreach ($persisted as $type => $modelsById) {
                    foreach ($modelsById as $id => $source) {
                        if (!$source instanceof Relatable) {
                            continue;
                        }

                        /**
                         * @var array<string, string> $relationships
                         */
                        $relationships = $source->getRelationships();

                        foreach ($relationships as $relationship => $method) {
                            if (!method_exists($source, $method)) {
                                continue;
                            }

                            $relationPayload = $source->resource?->relationships[Str::kebab($relationship)] ?? null;

                            if (!$relationPayload instanceof Relation) {
                                continue;
                            }

                            $relation = $source->{$method}();
                            $sourceKey = $type . ':' . (string) $id;

                            if ($relation instanceof BelongsTo && $relationPayload->data === null) {
                                if (
                                    !array_key_exists($sourceKey, $toOne)
                                    || !array_key_exists($method, $toOne[$sourceKey])
                                ) {
                                    $toOne[$sourceKey][$method] = null;
                                }
                            }

                            if ($relation instanceof BelongsToMany && $relationPayload->data === []) {
                                $toMany[$sourceKey][$method] ??= [];
                            }
                        }
                    }
                }
            },
            message: 'Preparing relational structure...',
        );

        if ($toMany !== []) {
            $this->startTimer('to-many-relationships');

            progress(
                label: 'Syncing to-many relationships...',
                steps: count($toMany),
                callback: function (int $index, Progress $progress) use ($toMany, $persisted): void {
                    $sourceKey = array_keys($toMany)[$index];
                    $relationships = $toMany[$sourceKey];

                    $source = $this->resolvePersistedFromKey($sourceKey, $persisted);

                    $eta = $this->getCountUpEstimate($progress->progress, $progress->total, 'to-many-relationships');

                    if ($eta !== null) {
                        $eta = " | ETA: {$eta}";
                    }

                    foreach ($relationships as $method => $targetIds) {
                        $source->{$method}()->sync($targetIds);

                        $progress->hint(
                            "Associated {$source->getBlueprint()} with "
                            . count($targetIds)
                            . " {$method} relationships{$eta}",
                        );
                    }
                },
            );
        }

        if ($toOne === []) {
            return;
        }

        $this->startTimer('to-one-relationships');

        progress(
            label: 'Syncing to-one relationships...',
            steps: count($toOne),
            callback: function (int $index, $progress) use ($toOne, $persisted): void {
                $sourceKey = array_keys($toOne)[$index];
                $relationships = $toOne[$sourceKey];

                $source = $this->resolvePersistedFromKey($sourceKey, $persisted);
                $eta = $this->getCountUpEstimate($progress->progress, $progress->total, 'to-one-relationships');

                if ($eta !== null) {
                    $eta = " | ETA: {$eta}";
                }

                foreach ($relationships as $method => $target) {
                    $relation = $source->{$method}();

                    if ($target === null) {
                        $relation->dissociate();
                        $source->save();

                        $progress->hint("Dissociated {$source->getBlueprint()} from {$method}{$eta}");

                        continue;
                    }

                    $relation->associate($target);
                    $source->save();

                    $progress->hint("Associated {$source->getBlueprint()} with a {$method} relationship{$eta}");
                }
            },
        );
    }
}
