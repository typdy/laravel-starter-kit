<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Runners;

use JsonException;
use Laravel\Prompts\Progress;
use Typdy\StarterKit\Laravel\Console\EloquentSync;
use Typdy\StarterKit\Laravel\Support\Sync\Concerns\HasTelemetry;
use Typdy\StarterKit\Laravel\Support\Sync\Data\FetchTaskData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\SyncStateData;
use Typdy\StarterKit\Laravel\Support\Sync\Hydrators\IncludedStateHydrator;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Utils\Arr;

use function array_key_exists;
use function ceil;
use function count;
use function implode;
use function in_array;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;
use function max;
use function strlen;

final class ConstructFetchRunner
{
    use HasTelemetry;

    /**
     * @var array<string, bool>
     */
    private array $skipTrimWarningShown = [];

    public function __construct(
        private readonly EloquentSync $command,
        private readonly int $pageSize = 20,
    ) {}

    /**
     * @param array<int, FetchTaskData> $tasks
     *
     * @throws JsonException
     * @throws DecodingException
     * @throws ResponseParserException
     *
     * @mago-expect analysis:possibly-undefined-string-array-index mago misses the $state defaults
     * @mago-expect analysis:possibly-null-argument $state is trusted
     * @mago-expect analysis:possibly-null-operand $state is trusted
     */
    public function run(SyncStateData $state, array $tasks): void
    {
        if ($tasks === []) {
            return;
        }

        foreach ($tasks as $task) {
            $added = new IncludedStateHydrator($this->command->getStore())->hydrate($state, $tasks);

            if ($added > 0) {
                info(
                    "Discovered {$added} included constructs to avoid fetching again.",
                );
            }

            $blueprint = $task->blueprint;

            $state->construct->fetchedPages[$blueprint] ??= [];
            $state->construct->constructsCount[$blueprint] ??= 0;
            $state->construct->blueprintFailures[$blueprint] ??= 0;

            $pages = $this->fetchTaskPageCount($state, $task);

            if ($pages === 0) {
                continue;
            }

            $this->startTimer();

            progress(
                label: "Fetching {$blueprint} constructs from typdy...",
                steps: $pages,
                callback: function (int $index, Progress $progress) use ($state, $task, $blueprint, $pages) {
                    $page = $index + 1;
                    $total = $pages;

                    $eta = $this->getCountUpEstimate($page, $total);

                    if ($eta !== null) {
                        $eta = " (ETA: {$eta})";
                    }

                    if (in_array($page, $state->construct->fetchedPages[$blueprint], strict: true)) {
                        $progress->hint("Skipped page {$page} of {$total} (resume){$eta}...");

                        return;
                    }

                    $progress->hint("Fetched page {$page} of {$total}{$eta}...");

                    $document = $this->command->request(
                        path: $task->path,
                        parameters: $this->buildRequestParameters(
                            blueprint: $blueprint,
                            state: $state,
                            pageNumber: $page,
                            pageSize: $this->pageSize,
                            parameters: $task->parameters,
                        ),
                    );

                    /** @var list<Resource> $relationships */
                    $resources = Arr::wrap($document->data ?? []);

                    $state->construct->constructsCount[$blueprint] += count($resources);

                    if ($document->response->getStatusCode() !== 200) {
                        $state->construct->blueprintFailures[$blueprint]++;
                    }

                    $state->construct->fetchedPages[$blueprint][] = $page;

                    $this->command->getStore()->storePageData($blueprint, $page, $document);
                    $this->command->getStore()->save($state);
                },
            );
        }
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function buildRequestParameters(
        string $blueprint,
        SyncStateData $state,
        array $parameters,
        int $pageNumber,
        int $pageSize,
    ): array {
        $parameters['page[number]'] = $pageNumber;
        $parameters['page[size]'] = $pageSize;

        $skip = $this->trimSkipIdentifiers($blueprint, $state->includedConstructs[$blueprint] ?? []);

        if ($skip !== []) {
            $parameters['filter[id][not]'] = implode(',', $skip);
        }

        return $parameters;
    }

    private function fetchTaskPageCount(SyncStateData $state, FetchTaskData $task): int
    {
        $blueprint = $task->blueprint;

        if (array_key_exists($blueprint, $state->construct->blueprintPages)) {
            return $state->construct->blueprintPages[$blueprint];
        }

        $document = spin(
            callback: fn () => $this->command->request(
                path: $task->path,
                parameters: $this->buildRequestParameters(
                    blueprint: $blueprint,
                    state: $state,
                    parameters: $task->parameters,
                    pageNumber: 1,
                    pageSize: 1,
                ),
            ),
            message: 'Fetching page counts from typdy...',
        );

        $total = max(0, (int) ($document->meta['total'] ?? 1));

        $state->construct->blueprintPages[$blueprint] = 0;

        if ($total !== 0) {
            $state->construct->blueprintPages[$blueprint] = max(1, (int) ceil($total / $this->pageSize));
        }

        $this->command->getStore()->save($state);

        // @mago-expect analysis:possibly-undefined-string-array-index set above
        return (int) $state->construct->blueprintPages[$blueprint];
    }

    /**
     * @param array<int, string> $skip
     *
     * @return array<int, string>
     */
    private function trimSkipIdentifiers(string $blueprint, array $skip): array
    {
        if ($skip === []) {
            return [];
        }

        $maxSkipChars = 1200;

        $trimmed = [];
        $length = 0;

        foreach ($skip as $id) {
            $idLength = strlen($id);
            $separator = $trimmed === [] ? 0 : 1;

            if (($length + $separator + $idLength) > $maxSkipChars) {
                break;
            }

            $trimmed[] = $id;
            $length += $separator + $idLength;
        }

        $shown = $this->skipTrimWarningShown[$blueprint] ?? false;

        if (count($trimmed) < count($skip) && !$shown) {
            warning('Skipping some identifiers to avoid exceeding the maximum length of the request parameters.');

            $this->skipTrimWarningShown[$blueprint] = true;
        }

        return $trimmed;
    }
}
