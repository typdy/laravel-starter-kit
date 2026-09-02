<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Laravel\Console\Concerns\HasAtomicLocks;
use Typdy\StarterKit\Laravel\Console\Concerns\HasModelResolution;
use Typdy\StarterKit\Laravel\Console\Concerns\HasRepoResolution;
use Typdy\StarterKit\Laravel\Console\Concerns\HasScope;
use Typdy\StarterKit\Laravel\Console\Concerns\HasSyncState;
use Typdy\StarterKit\Laravel\Console\Concerns\RetriesRequests;
use Typdy\StarterKit\Laravel\Support\Sync\Data\IncludeDiscoveryData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\SyncStateData;
use Typdy\StarterKit\Laravel\Support\Sync\FetchPlanner;
use Typdy\StarterKit\Laravel\Support\Sync\IncludeDiscovery;
use Typdy\StarterKit\Laravel\Support\Sync\Runners\ConstructFetchRunner;
use Typdy\StarterKit\Laravel\Support\Sync\Runners\GlobalFetchRunner;
use Typdy\StarterKit\Laravel\Support\Sync\Runners\PersistenceRunner;
use Typdy\StarterKit\Laravel\Support\Sync\Transformers\ContentTransformer;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Utils\Arr;

use function array_map;
use function count;
use function implode;
use function in_array;
use function is_string;
use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function max;

final class EloquentSync extends Command implements SignalableCommandInterface
{
    public const int EXIT_LOCK_BUSY = 10;

    use HasAtomicLocks;
    use HasScope;
    use HasModelResolution;
    use HasRepoResolution;
    use HasSyncState;
    use RetriesRequests;

    protected $name = 'typdy:eloquent:sync';

    protected $description = 'Sync typdy content to your Eloquent database.';

    #[Override]
    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handle(
        ResolvesModels $modelResolver,
        ResolvesRepositories $repoResolver,
        RequestCoordinator $api,
    ): int {
        $this->modelResolver = $modelResolver;
        $this->repoResolver = $repoResolver;
        $this->api = $api;

        if (!$this->acquireLock()) {
            return self::EXIT_LOCK_BUSY;
        }

        $this->applyApiScope();

        try {
            $models = $this->resolveModels();
            $nonGlobalRepos = $this->resolveRepos(excludeGlobal: true);

            if ($models === []) {
                info('Nothing to sync.');

                return Command::SUCCESS;
            }

            $state = $this->initializeSyncState($this->resolveModelBlueprints());

            $this->showResumeStatus($state);

            $includes = spin(
                callback: static fn () => new IncludeDiscovery()->discover($models),
                message: 'Learning include paths...',
            );

            $constructId = $this->resolveScopedConstructId();

            if ($constructId !== null) {
                $this->syncSingleConstruct($state, $constructId, $includes);
            } else {
                // we need to plan the fetch order to optimize the number of API requests
                $plan = spin(
                    callback: static fn () => new FetchPlanner()->build($models, $includes, $nonGlobalRepos),
                    message: 'Building fetch plan...',
                );

                // globals are always first with all of their includes
                new GlobalFetchRunner($this)->run($state, $plan->global, $includes);

                // now we can start fetching constructs we don't already have in state
                new ConstructFetchRunner($this)->run($state, $plan->initial);
            }

            if (!$this->showSummary($models, $state)) {
                note('Sync cancelled.');

                return Command::SUCCESS;
            }

            $content = new ContentTransformer($this)->transform($state);

            new PersistenceRunner()->run($content, $models);

            $this->store->clear();

            return Command::SUCCESS;
        } catch (Throwable $e) {
            info('Sync interrupted. You can resume this session with --resume once the issue is resolved.');

            $this->error($e::class . ': ' . $e->getMessage());

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        } finally {
            $this->releaseLock();
        }
    }

    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = false): int|false
    {
        info('Command cancelled. Releasing lock...');

        $this->releaseLock(force: true);

        return 0;
    }

    #[Override]
    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Bypass lock checks and force a new sync run.'],
            ['resume', null, InputOption::VALUE_NONE, 'Resume from the last saved sync state for this scope.'],
            ['retries', null, InputOption::VALUE_REQUIRED, 'Number of retry attempts for retryable API failures.', 3],
            ['team', null, InputOption::VALUE_REQUIRED, 'Team identifier to scope the sync.'],
            ['project', null, InputOption::VALUE_REQUIRED, 'Project identifier to scope the sync.'],
            ['blueprint', null, InputOption::VALUE_REQUIRED, 'Blueprint identifier to scope the sync.'],
            ['id', null, InputOption::VALUE_REQUIRED, 'Construct id to scope the sync to a single construct.'],
        ];
    }

    private function resolveScopedConstructId(): ?int
    {
        $id = $this->option('id');

        if ($id === null || $id === '') {
            return null;
        }

        $id = (int) $id;

        $blueprint = $this->option('blueprint');

        if (!is_string($blueprint) || $blueprint === '') {
            throw new InvalidArgumentException('The --id option requires a --blueprint option.');
        }

        return $id;
    }

    private function showResumeStatus(SyncStateData $state): void
    {
        $blueprintPages = $state->construct->blueprintPages;
        $fetchedPages = $state->construct->fetchedPages;

        $total = 0;
        $done = 0;

        foreach ($blueprintPages as $blueprint => $count) {
            $total += (int) $count;
            $done += count($fetchedPages[$blueprint] ?? []);
        }

        $globalCompleted = $state->global->completed;
        $globalDone = max(0, count($state->global->blueprints) - count($state->global->failed));

        $hasResumeData = $globalCompleted || $done > 0 || count($blueprintPages) > 0;

        if ($this->option('resume') && $hasResumeData) {
            callout(
                label: 'Resume Status',
                content: "Resuming from saved sync state. Pages previously fetched: {$done}/{$total}. Globals previously indexed: {$globalDone}.",
            );

            return;
        }
    }

    /**
     * @param array<int, Construct&Model> $models
     */
    private function showSummary(array $models, SyncStateData $state): bool
    {
        if (!$this->input->isInteractive()) {
            return true;
        }

        callout(
            label: 'Sync Summary',
            content: 'The following is a summary of the content that will be synced to the database. Please review the information below before proceeding.',
        );

        table(
            headers: ['Team', 'Project', 'Blueprint', 'Items', 'Pages', 'Global', 'Status'],
            rows: array_map(
                static function (Construct $model) use ($state): array {
                    $blueprint = $model->getBlueprint();

                    $isGlobal = in_array($blueprint, $state->global->blueprints, strict: true);

                    $items = (int) ($state->construct->constructsCount[$blueprint] ?? 0);
                    $items += count($state->includedConstructs[$blueprint] ?? []);

                    $pages = count($state->construct->fetchedPages[$blueprint] ?? []);
                    $failures = (int) ($state->construct->blueprintFailures[$blueprint] ?? 0);

                    if ($isGlobal) {
                        $items++;
                        $pages++;

                        if (in_array($blueprint, $state->global->failed, strict: true)) {
                            $failures++;
                        }
                    }

                    $message = $failures > 0 ? "{$failures} failed" : 'All good';

                    return [
                        $model->getTeam(),
                        $model->getProject(),
                        $blueprint,
                        (string) $items,
                        (string) $pages,
                        $isGlobal ? '1' : '0',
                        $message,
                    ];
                },
                $models,
            ),
        );

        return confirm('Proceed with syncronization of the above content?', default: true);
    }

    private function syncSingleConstruct(
        SyncStateData $state,
        int $constructId,
        IncludeDiscoveryData $includes,
    ): void {
        [, , $blueprint] = $this->getScope();

        if ($blueprint === null) {
            throw new InvalidArgumentException('Single construct sync requires a blueprint scope.');
        }

        $includePaths = [
            ...($includes->blueprintPaths[$blueprint] ?? []),
            ...($includes->deferredBlueprintPaths[$blueprint] ?? []),
        ];

        $parameters = [];

        if ($includePaths !== []) {
            $parameters['include'] = implode(',', $includePaths);
        }

        $document = $this->request(
            path: 'constructs/' . $blueprint . '/' . $constructId,
            parameters: $parameters,
        );

        $state->construct->blueprintPages[$blueprint] = 1;
        $state->construct->fetchedPages[$blueprint] = [1];
        $state->construct->blueprintFailures[$blueprint] = $document->response->getStatusCode() === 200 ? 0 : 1;

        /** @var list<Resource> $resources */
        $resources = Arr::wrap($document->data ?? []);

        $state->construct->constructsCount[$blueprint] = count($resources);

        $this->store->storePageData($blueprint, 1, $document);
        $this->store->save($state);
    }
}
