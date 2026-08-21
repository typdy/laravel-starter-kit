<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Runners;

use Laravel\Prompts\Progress;
use Typdy\StarterKit\Laravel\Console\EloquentSync;
use Typdy\StarterKit\Laravel\Support\Sync\Concerns\HasTelemetry;
use Typdy\StarterKit\Laravel\Support\Sync\Data\FetchTaskData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\IncludeDiscoveryData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\SyncStateData;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Utils\Arr;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

final class GlobalFetchRunner
{
    use HasTelemetry;

    public function __construct(
        private readonly EloquentSync $command,
    ) {}

    public function run(SyncStateData $state, FetchTaskData $task, IncludeDiscoveryData $includes): void
    {
        if ($state->global->completed) {
            return;
        }

        $resources = $this->fetchGlobals($state, $task);

        if ($resources === []) {
            $state->global->completed = true;

            $this->command->getStore()->save($state);

            return;
        }

        $this->startTimer();

        progress(
            label: 'Fetching global constructs from typdy...',
            steps: $resources,
            callback: fn (Resource $resource, Progress $progress) => $this->fetchGlobalConstruct(
                $state,
                $resource,
                $includes,
                $progress,
            ),
        );

        $state->global->completed = true;

        $this->command->getStore()->save($state);
    }

    private function fetchGlobalConstruct(
        SyncStateData $state,
        Resource $resource,
        IncludeDiscoveryData $includes,
        Progress $progress,
    ): void {
        if (
            !in_array($resource->type, $state->supportedBlueprints, strict: true)
            && !in_array($resource->type, $state->global->failed, strict: true)
        ) {
            return;
        }

        // response sizes are already small, so we are safe to include everything
        $includes = [
            ...($includes->blueprintPaths[$resource->type] ?? []),
            ...($includes->deferredBlueprintPaths[$resource->type] ?? []),
        ];

        $parameters = [];

        if ($includes !== []) {
            $parameters['include'] = implode(',', $includes);
        }

        $document = $this->command->request(
            path: 'globals/' . $resource->id,
            parameters: $parameters,
        );

        $remaining = count($state->global->failed) - 1;
        $total = count($state->global->blueprints);

        $eta = $this->getCountDownEstimate($remaining, $total);

        if ($eta !== null) {
            $eta = " (ETA: {$eta})";
        }

        if ($document->response->getStatusCode() === 200) {
            $state->global->failed = array_filter(
                $state->global->failed,
                static fn (string $blueprint): bool => $blueprint !== $resource->type,
            )
                |> array_values(...);
        }

        $progress->hint("Fetched global {$resource->type}, {$remaining} remaining{$eta}...");

        $this->command->getStore()->storePageData(
            blueprint: $resource->type,
            page: 0, // we'll just prepend the global to the page set
            document: $document,
        );

        $this->command->getStore()->save($state);
    }

    /**
     * @return list<Resource>
     */
    private function fetchGlobals(SyncStateData $state, FetchTaskData $task): array
    {
        $document = spin(
            callback: fn () => $this->command->request($task->path, $task->parameters),
            message: 'Fetching globals from typdy...',
        );

        /** @var list<Resource> $resources  */
        $resources = Arr::wrap($document->data ?? []);

        // found supported global blueprints
        $state->global->blueprints = array_map(
            static fn (Resource $resource): string => $resource->type,
            array_filter($resources, static fn (Resource $resource): bool => in_array(
                $resource->type,
                $state->supportedBlueprints,
                strict: true,
            ))
                |> array_values(...),
        );

        // we'll remove successes later
        $state->global->failed = $state->global->blueprints;

        $this->command->getStore()->storeGlobalData($document);
        $this->command->getStore()->save($state);

        return $resources;
    }
}
