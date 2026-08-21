<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Hydrators;

use JsonException;
use Typdy\StarterKit\Laravel\Support\Sync\Data\FetchTaskData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\SyncStateData;
use Typdy\StarterKit\Laravel\Support\Sync\StateStore;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Utils\Arr;

use function array_filter;
use function array_map;
use function array_unique;
use function in_array;

final readonly class IncludedStateHydrator
{
    public function __construct(
        private StateStore $store,
    ) {}

    /**
     * @param array<int, FetchTaskData> $tasks
     *
     * @throws JsonException
     * @throws DecodingException
     * @throws ResponseParserException
     */
    public function hydrate(SyncStateData $state, array $tasks): int
    {
        if ($tasks === []) {
            return 0;
        }

        $targets = $this->getBlueprintsFromTasks($tasks);

        // media is include only, so must be recorded here as we can't fetch it directly
        $targets[] = 'media';

        $added = 0;

        foreach ($this->store->getDocuments($state) as $document) {
            /** @var list<Resource> $relationships */
            $relationships = Arr::wrap($document->data ?? []);

            $resources = [...$relationships, ...$document->included];

            foreach ($resources as $resource) {
                $blueprint = $resource->type;

                if (!in_array($blueprint, $targets, strict: true)) {
                    continue;
                }

                $state->includedConstructs[$blueprint] ??= [];

                if (!in_array($resource->id, $state->includedConstructs[$blueprint], strict: true)) {
                    $state->includedConstructs[$blueprint][] = $resource->id;
                    $added++;
                }
            }
        }

        if ($added > 0) {
            $this->store->save($state);
        }

        return $added;
    }

    /**
     * @param array<int, FetchTaskData> $tasks
     *
     * @return array<int, string>
     */
    private function getBlueprintsFromTasks(array $tasks): array
    {
        return array_map(
            static fn (FetchTaskData $task): string => $task->blueprint,
            $tasks,
        )
            |> array_filter(...)
            |> array_unique(...);
    }
}
