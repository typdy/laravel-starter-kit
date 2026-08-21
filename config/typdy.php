<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Resolvers\ModelResolver;
use Typdy\StarterKit\Laravel\Storage\EloquentDriver;
use Typdy\StarterKit\Laravel\Storage\Mutators\EloquentQueryMutator;
use Typdy\StarterKit\Laravel\Webhooks\Handlers\EloquentSyncHandler;
use Typdy\StarterKit\Resolvers\RepositoryResolver;
use Typdy\StarterKit\Sync\DriverPipeline;

// use Typdy\StarterKit\Webhooks\Handlers\UpdateStorageHandler;
// use Typdy\StarterKit\Webhooks\QueueReplayDispatcher;

return [
    /*
     |--------------------------------------------------------------------------
     | Default Team and Project
     |--------------------------------------------------------------------------
     |
     | You must set a default team and project to connect to. If you are a
     | member of multiple teams or projects, you can override these values in
     | your repositories.
     |
     */
    'team' => env('TYPDY_TEAM', 'team'),

    'project' => env('TYPDY_PROJECT', 'project'),

    /*
     |--------------------------------------------------------------------------
     | OAuth Credentials
     |--------------------------------------------------------------------------
     |
     | You can configure your OAuth credentials here. Use the `php artisan
     | typdy:connect` command to generate an authorization code.
     |
     | The redirect URI should point to the `/display-code` route if you are
     | using the connect command. This command and route are only available in
     | your local environment.
     |
     | Remember your authorization code gives access to a user account,
     | not just a team or project.
     |
     | You should never share these credentials with anyone!
     |
     */
    'oauth' => [
        'client_id' => env('TYPDY_CLIENT_ID', null),

        'client_secret' => env('TYPDY_CLIENT_SECRET', null),

        'redirect_uri' => env('TYPDY_REDIRECT_URI', 'http://127.0.0.1:8000/display-code'),

        'auth_code' => env('TYPDY_AUTH_CODE', null),

        'scopes' => explode(',', env('TYPDY_SCOPES', 'delivery')),
    ],

    /*
     |--------------------------------------------------------------------------
     | Personal Access Token
     |--------------------------------------------------------------------------
     |
     | Alternatively, you can use a personal access token to connect to typdy.
     |
     | This is simpler than using OAuth credentials, but it is less secure.
     |
     | Personal access tokens can not be automatically refreshed, so you will
     | need to generate a new token when it expires.
     |
     | This is generally only recommended for development.
     |
     */
    'token' => env('TYPDY_TOKEN', null),

    /*
     |--------------------------------------------------------------------------
     | Private Storage Path
     |--------------------------------------------------------------------------
     |
     | This path will be used to store your OAuth token, data from file type
     | storage drivers, and other data that should not be publically available.
     |
     */
    'private_storage_path' => storage_path('app/typdy'),

    /*
     |--------------------------------------------------------------------------
     | Model and Repository Resolvers
     |--------------------------------------------------------------------------
     |
     | In order to locate your models and repositories, resolvers are needed.
     | The default resolvers cover most use cases, but if you need a different
     | structure, you can override the resolvers here.
     |
     */
    'model_resolver' => ModelResolver::class,

    'model_locations' => [
        // location of the default media model provided by the starter kit
        'Typdy\\StarterKit\\Laravel\\Models' => dirname(__DIR__) . '/vendor/typdy/laravel-starter-kit/src/Models',

        // configure the location of your own models here
        'App\\Models' => app_path('Models'),
    ],

    'repository_resolver' => RepositoryResolver::class,

    'repository_locations' => [
        // location of the default global repository provided by the starter kit
        'Typdy\\StarterKit\\Repositories' => dirname(__DIR__) . '/vendor/typdy/php-starter-kit/src/Repositories',

        // configure the location of your own repositories here
        'App\\Repositories' => app_path('Repositories'),
    ],

    /*
     |--------------------------------------------------------------------------
     | API Interaction
     |--------------------------------------------------------------------------
     |
     | Control how the typdy API is interacted with.
     |
     | response_failure_exceptions: If set to true, exceptions will be thrown
     | when a request fails. If set to false, the response document will be
     | returned instead. When set to true, you can override this behaviour on a
     | per-request basis by calling the `returnsOnError` method of the
     | repository.
     |
     | legacy_types: If set to true, the typdy API will return legacy types for
     | json:api requests. If set to false, the typdy API will return the new
     | types. New types are not available to all users yet, so you may need to
     | set this to true until they are.
     |
     */
    'response_failure_exceptions' => env('TYPDY_RESPONSE_FAILURE_EXCEPTIONS', true),

    'legacy_types' => env('TYPDY_LEGACY_TYPES', true),

    /*
     |--------------------------------------------------------------------------
     | Storage Drivers
     |--------------------------------------------------------------------------
     |
     | Storage drivers are used to cache or store data retrieved from the typdy
     | API.
     |
     | Only a single DatabaseDriver may be used and it must be placed last in
     | the drivers stack. This is because, when a database driver is used, it
     | will be treated as the source of truth for all cached data (drivers
     | above it).
     |
     | If a database driver is not used, the source of truth will be the typdy
     | API itself. This means that after invalidation, data will be re-fetched
     | from the API, which is slower.
     |
     | Drivers will promote data up the driver stack, so it's important to
     | place the most performant drivers at the top of the stack.
     |
     | The driver pipeline can be configured if you need to customize the way
     | drivers are used.
     |
     */
    'drivers' => [
        EloquentDriver::class,
    ],

    'driver_pipline' => DriverPipeline::class,

    // when we read data from lower drivers, we will promote it to higher
    //  drivers. Alternatively, you'll need to manually promote data.
    'promote_read_hits' => env('TYPDY_PROMOTE_READ_HITS', true),

    // when stored data is older than this value, we'll invalidate it and fetch
    //  from the source of truth (typdy API or database driver)
    'max_cache_age_days' => env('TYPDY_MAX_CACHE_AGE_DAYS', 90),

    // when data is updated, all other copies of that data will be updated to
    //  match the new data. Otherwise, we'll just invalidate the other copies
    'mutate_stored_payloads' => env('TYPDY_MUTATE_STORED_PAYLOADS', false),

    // request data is used to generate unique storage hashes, somtimes it's
    //  necessary to modify this data prior to hash generateion
    'request_mutators' => [
        // ensure the query is converted to a unique string
        EloquentQueryMutator::class,
    ],

    /*
     |--------------------------------------------------------------------------
     | Webhooks Handlers
     |--------------------------------------------------------------------------
     |
     | Configure webhooks with a secret and handlers.
     |
     | EloquentSyncHandler: This handler syncs typdy updates to your Eloquent
     | database. Supported options - tries: int, backoff: list<int>, delay: int,
     | queue: string.
     |
     | UpdateStorageHandler: This handler invalidates and updates, or mutates
     | stored data for each non-database driver. Supported options - tries: int,
     | backoff: list<int>, delay: int, queue: string.
     |
     */
    'webhooks' => [
        'default' => [
            'team' => null, // default team
            'project' => null, // default project
            'secret' => env('TYPDY_WEBHOOK_SECRET', null),
            'handlers' => [
                EloquentSyncHandler::class => [
                    'tries' => 5,
                    'backoff' => [10, 30, 60, 120],
                    'delay' => 5,
                    'queue' => env('TYPDY_WEBHOOK_QUEUE', 'default'),
                ],
                // UpdateStorageHandler::class => [
                //     'tries' => 5,
                //     'backoff' => [10, 30, 60, 120],
                //     'delay' => 5,
                //     'queue' => env('TYPDY_WEBHOOK_QUEUE', 'default'),
                //     'dispatcher' => QueueReplayDispatcher::class,
                // ],
            ],
        ],
    ],
];

