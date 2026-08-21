<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Override;
use Typdy\StarterKit\Containers\LaravelAdaptor;
use Typdy\StarterKit\Contracts\CollectCallback;
use Typdy\StarterKit\Contracts\DecollectCallback;
use Typdy\StarterKit\Contracts\PaginateCallback;
use Typdy\StarterKit\Data\Paginated;
use Typdy\StarterKit\Laravel\Console\EloquentMigrations;
use Typdy\StarterKit\Laravel\Console\EloquentSync;
use Typdy\StarterKit\Laravel\Resolvers\ModelResolver;
use Typdy\StarterKit\Laravel\Webhooks\Contracts\InvalidatesNonDatabaseCache;
use Typdy\StarterKit\Laravel\Webhooks\Support\NonDatabaseCacheInvalidator;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Repositories\GlobalRepository;
use Typdy\StarterKit\Repositories\Repository;
use Typdy\StarterKit\Resolvers\RepositoryResolver;
use Typdy\StarterKit\Sync\DriverPipeline;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;
use Typdy\StarterKit\Webhooks\Contracts\QueuesReplayTasks;
use Typdy\StarterKit\Webhooks\Contracts\ReplayDispatcher;
use Typdy\StarterKit\Webhooks\Laravel\LaravelQueueAdaptor;
use Typdy\StarterKit\Webhooks\QueueReplayDispatcher;

use function abort;
use function array_unique;
use function collect;
use function config;
use function dirname;
use function iterator_to_array;
use function request;
use function storage_path;

final class ServiceProvider extends IlluminateServiceProvider
{
    public static function configureStarterKit(): void {}

    public function boot(): void
    {
        $this->publishConfig();
        $this->loadRoutes();
    }

    /**
     * @mago-expect analysis:mixed-argument config() returns mixed
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfig();
        $this->registerCommands();
        $this->registerMigrations();
        $this->registerWebhooks();

        // @mago-expect analysis:possibly-invalid-argument app interface extends container
        Typdy::$container = new LaravelAdaptor($this->app);

        Typdy::$config = new TypdyConfig(
            team: config('typdy.team', default: 'team'),
            project: config('typdy.project', default: 'project'),

            token: config('typdy.token'),

            clientId: config('typdy.oauth.client_id'),
            clientSecret: config('typdy.oauth.client_secret'),
            redirectUrl: config('typdy.oauth.redirect_url'),
            authCode: config('typdy.oauth.auth_code'),
            // @mago-expect analysis:invalid-array-element
            // @mago-expect analysis:possibly-invalid-argument
            scopes: array_unique([...config('typdy.oauth.scopes', default: []), 'access-user-data']),

            privateStoragePath: config('typdy.private_storage_path', default: storage_path('app/typdy')),

            repositoryResolver: config('typdy.repository_resolver', default: RepositoryResolver::class),
            repositoryLocations: config('typdy.repository_locations', default: []),

            modelResolver: config('typdy.model_resolver', default: ModelResolver::class),
            modelLocations: config('typdy.model_locations', default: []),

            responseFailureExceptions: config('typdy.response_failure_exceptions', default: true),
            legacyTypes: config('typdy.legacy_types', default: true),

            drivers: config('typdy.drivers', default: []),

            driverPipeline: config('typdy.driver_pipeline', default: DriverPipeline::class),
            promoteReadHits: config('typdy.promote_read_hits', default: true),
            maxCacheAgeDays: config('typdy.max_cache_age_days', default: 90),
            mutateStoredPayloads: config('typdy.mutate_stored_payloads', default: false),
            requestMutators: config('typdy.request_mutators', default: []),
        );

        Typdy::$collectCallback = new class() implements CollectCallback {
            #[Override]
            public function __invoke(iterable $data = []): iterable
            {
                return collect($data);
            }
        };

        Typdy::$decollectCallback = new class() implements DecollectCallback {
            #[Override]
            public function __invoke(iterable $data = []): array
            {
                return $data instanceof Collection ? $data->all() : iterator_to_array($data);
            }
        };

        Typdy::$paginateCallback = new class() implements PaginateCallback {
            #[Override]
            public function __invoke(Paginated $models, Request $request): iterable
            {
                return new LengthAwarePaginator(
                    items: $models->items,
                    total: $models->total,
                    perPage: $models->perPage,
                    currentPage: $models->currentPage,
                    options: [
                        'path' => request()->url(),
                    ],
                );
            }
        };

        Repository::macro('resolvePageNumber', LengthAwarePaginator::resolveCurrentPage(...));
        Repository::macro('throwNotFoundException', static fn () => abort(code: 404, message: 'Construct not found.'));

        GlobalRepository::macro('resolvePageNumber', LengthAwarePaginator::resolveCurrentPage(...));
        GlobalRepository::macro('throwNotFoundException', static fn () => abort(
            code: 404,
            message: 'Construct not found.',
        ));

        Typdy::register();
    }

    protected function loadRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }

    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/typdy.php', 'typdy');
    }

    protected function publishConfig(): void
    {
        $this->publishes([dirname(__DIR__) . '/config/' => config_path()], 'config');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            EloquentMigrations::class,
            EloquentSync::class,
        ]);
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/migrations');
    }

    protected function registerWebhooks(): void
    {
        $this->app->singleton(
            QueuesReplayTasks::class,
            fn (): LaravelQueueAdaptor => new LaravelQueueAdaptor(dispatcher: $this->app->make(Dispatcher::class)),
        );

        $this->app->singleton(ReplayDispatcher::class, QueueReplayDispatcher::class);
        $this->app->singleton(InvalidatesNonDatabaseCache::class, NonDatabaseCacheInvalidator::class);
    }
}
