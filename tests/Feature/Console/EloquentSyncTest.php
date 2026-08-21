<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Typdy\StarterKit\Api\Contracts\Client as ClientContract;
use Typdy\StarterKit\Laravel\Models\Media;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

uses(TestCase::class);

beforeEach(function () {
    $this->privateStoragePath = base_path('workbench/storage/framework/testing/typdy-sync');

    File::deleteDirectory($this->privateStoragePath);
    File::ensureDirectoryExists($this->privateStoragePath);

    Typdy::$config = new TypdyConfig(
        team: 'team-test',
        project: 'project-test',
        privateStoragePath: $this->privateStoragePath,
    );

    Schema::dropIfExists('media');

    Schema::create('media', function (Blueprint $table): void {
        $table->id();
        $table->string('identifier')->nullable();
        $table->string('team');
        $table->string('project');

        $table->string('name')->nullable();
        $table->text('url')->nullable();
        $table->json('conversions')->nullable();
        $table->json('conversionsInProgress')->nullable();

        $table->json('translations')->nullable();
        $table->json('resource');

        $table->timestamp('created');
        $table->timestamp('updated');
    });
});

afterEach(function () {
    Schema::dropIfExists('media');

    File::deleteDirectory($this->privateStoragePath);
});

it('runs the primary interactive sync flow and persists constructs', function () {
    $client = mock(ClientContract::class);

    $client->shouldReceive('request')->andReturn(
        new Response(200, body: '{"data": []}'),
        new Response(200, body: '{"data": [], "meta": {"total": 1}}'),
        new Response(
            200,
            body: <<<'JSON'
            {"data":[{"type":"media","id":"101","attributes":{"identifier":"hero-image","name":"Hero Image","url":"https://cdn.example.test/hero.jpg","conversions":{},"conversionsInProgress":[],"translations":{},"created":"2026-01-01T00:00:00+00:00","updated":"2026-01-01T00:00:00+00:00"}}]}
            JSON,
        ),
    );

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync')
        ->expectsPromptsTable(
            headers: ['Team', 'Project', 'Blueprint', 'Items', 'Pages', 'Global', 'Status'],
            rows: [['team-test', 'project-test', 'media', '1', '1', '0', 'All good']],
        )
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    $media = Media::query()->find(101);

    expect($media)->not->toBeNull();
    expect($media?->identifier)->toBe('hero-image');
    expect($media?->team)->toBe('team-test');
    expect($media?->project)->toBe('project-test');

    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';

    expect(file_exists($this->privateStoragePath . '/sync/' . $lockName . '.json'))->toBeFalse();
    expect(file_exists($this->privateStoragePath . '/sync/' . $lockName))->toBeFalse();
});

it('syncs a single construct when id and blueprint options are provided', function () {
    $client = mock(ClientContract::class);

    $client
        ->shouldReceive('request')
        ->once()
        ->andReturn(
            new Response(
                200,
                body: <<<'JSON'
                {"data":{"type":"media","id":"101","attributes":{"identifier":"hero-image","name":"Hero Image","url":"https://cdn.example.test/hero.jpg","conversions":{},"conversionsInProgress":[],"translations":{},"created":"2026-01-01T00:00:00+00:00","updated":"2026-01-01T00:00:00+00:00"}}}
                JSON,
            ),
        );

    app()->instance(ClientContract::class, $client);

    $model = new Media();

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'media')
        ->andReturn($model);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'media')
        ->andReturn($repo);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--blueprint' => 'media', '--id' => 101])
        ->expectsPromptsTable(
            headers: ['Team', 'Project', 'Blueprint', 'Items', 'Pages', 'Global', 'Status'],
            rows: [['team-test', 'project-test', 'media', '1', '1', '0', 'All good']],
        )
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    $media = Media::query()->find(101);

    expect($media)->not->toBeNull();
    expect($media?->identifier)->toBe('hero-image');
});

it('fails when id option is provided without a blueprint option', function () {
    $client = mock(ClientContract::class);
    $client->shouldNotReceive('request');

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--id' => 101])
        ->expectsOutputToContain('The --id option requires a --blueprint option.')
        ->assertExitCode(1);
});

it('returns early when no models are resolved', function () {
    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([]);

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync')
        ->expectsPromptsInfo('Nothing to sync.')
        ->assertSuccessful();
});

it('fails when a sync lock already exists', function () {
    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';

    $lock = cache()->lock($lockName, 3600);

    expect($lock->get())->toBeTrue();

    try {
        $this
            ->artisan('typdy:eloquent:sync')
            ->expectsOutputToContain('Another typdy sync is already running')
            ->assertExitCode(10);
    } finally {
        $lock->release();
    }
});

it('bypasses an existing lock when force is enabled', function () {
    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';

    $existingLock = cache()->lock($lockName, 3600);

    expect($existingLock->get())->toBeTrue();

    $client = mock(ClientContract::class);

    $client->shouldReceive('request')->andReturn(
        new Response(200, body: '{"data": []}'),
        new Response(200, body: '{"data": [], "meta": {"total": 1}}'),
        new Response(
            200,
            body: <<<'JSON'
            {"data":[{"type":"media","id":"101","attributes":{"identifier":"hero-image","name":"Hero Image","url":"https://cdn.example.test/hero.jpg","conversions":{},"conversionsInProgress":[],"translations":{},"created":"2026-01-01T00:00:00+00:00","updated":"2026-01-01T00:00:00+00:00"}}]}
            JSON,
        ),
    );

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    try {
        $this
            ->artisan('typdy:eloquent:sync', ['--force' => true])
            ->expectsPromptsInfo('Force option enabled. Bypassing lock check.')
            ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
            ->assertSuccessful();
    } finally {
        cache()->lock($lockName, 3600)->forceRelease();
    }

    expect(Media::query()->find(101))->not->toBeNull();
});

it('resumes from existing sync state when resume option is enabled', function () {
    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';
    $syncRoot = $this->privateStoragePath . '/sync';
    $statePath = $syncRoot . '/' . $lockName . '.json';
    $runDirectory = $syncRoot . '/' . $lockName;

    File::ensureDirectoryExists($syncRoot);

    file_put_contents(
        $statePath,
        json_encode([
            'runDirectory' => $runDirectory,
            'supportedBlueprints' => ['media'],
            'includedConstructs' => [],
            'global' => [
                'blueprints' => [],
                'failed' => [],
                'completed' => true,
            ],
            'construct' => [
                'blueprintPages' => ['media' => 1],
                'fetchedPages' => ['media' => [1]],
                'constructsCount' => ['media' => 1],
                'blueprintFailures' => ['media' => 0],
            ],
        ], JSON_THROW_ON_ERROR),
    );

    $client = mock(ClientContract::class);
    $client->shouldNotReceive('request');

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--resume' => true])
        ->expectsOutputToContain('Resuming from saved sync state.')
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    expect(file_exists($statePath))->toBeFalse();
    expect(file_exists($runDirectory))->toBeFalse();
});

it('fails with a resumable message when resume state json is invalid', function () {
    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';
    $syncRoot = $this->privateStoragePath . '/sync';
    $statePath = $syncRoot . '/' . $lockName . '.json';

    File::ensureDirectoryExists($syncRoot);

    file_put_contents($statePath, data: '{not-valid-json');

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--resume' => true])
        ->expectsOutputToContain(
            'Sync interrupted. You can resume this session with --resume once the issue is resolved.',
        )
        ->expectsOutputToContain('JsonException')
        ->assertExitCode(1);

    expect($statePath)->toBeFile();
});

it('resumes a partial state and fetches only remaining pages', function () {
    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';
    $syncRoot = $this->privateStoragePath . '/sync';
    $statePath = $syncRoot . '/' . $lockName . '.json';
    $runDirectory = $syncRoot . '/' . $lockName;

    File::ensureDirectoryExists($syncRoot);

    file_put_contents(
        $statePath,
        json_encode([
            'runDirectory' => $runDirectory,
            'supportedBlueprints' => ['media'],
            'includedConstructs' => [],
            'global' => [
                'blueprints' => [],
                'failed' => [],
                'completed' => true,
            ],
            'construct' => [
                'blueprintPages' => ['media' => 2],
                'fetchedPages' => ['media' => [1]],
                'constructsCount' => ['media' => 1],
                'blueprintFailures' => ['media' => 0],
            ],
        ], JSON_THROW_ON_ERROR),
    );

    $client = mock(ClientContract::class);

    $client
        ->shouldReceive('request')
        ->once()
        ->andReturn(
            new Response(
                200,
                body: <<<'JSON'
                {"data":[{"type":"media","id":"102","attributes":{"identifier":"resumed-image","name":"Resumed Image","url":"https://cdn.example.test/resumed.jpg","conversions":{},"conversionsInProgress":[],"translations":{},"created":"2026-01-02T00:00:00+00:00","updated":"2026-01-02T00:00:00+00:00"}}]}
                JSON,
            ),
        );

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--resume' => true])
        ->expectsOutputToContain('Resuming from saved sync state')
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    expect(Media::query()->find(102))->not->toBeNull();
    expect(file_exists($statePath))->toBeFalse();
});

it('cancels before persistence when confirmation is declined', function () {
    $client = mock(ClientContract::class);

    $client->shouldReceive('request')->andReturn(
        new Response(200, body: '{"data": []}'),
        new Response(200, body: '{"data": [], "meta": {"total": 1}}'),
        new Response(
            200,
            body: <<<'JSON'
            {"data":[{"type":"media","id":"201","attributes":{"identifier":"cancelled-image","name":"Cancelled Image","url":"https://cdn.example.test/cancelled.jpg","conversions":{},"conversionsInProgress":[],"translations":{},"created":"2026-01-03T00:00:00+00:00","updated":"2026-01-03T00:00:00+00:00"}}]}
            JSON,
        ),
    );

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync')
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'no')
        ->expectsOutputToContain('Sync cancelled.')
        ->assertSuccessful();

    expect(Media::query()->find(201))->toBeNull();

    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';

    expect($this->privateStoragePath . '/sync/' . $lockName . '.json')->toBeFile();
});

it('retries retryable api failures and completes successfully', function () {
    $client = mock(ClientContract::class);

    $client->shouldReceive('request')->andReturn(
        new Response(500, body: '{"data": []}'),
        new Response(200, body: '{"data": []}'),
        new Response(200, body: '{"data": [], "meta": {"total": 1}}'),
        new Response(
            200,
            body: <<<'JSON'
            {"data":[{"type":"media","id":"301","attributes":{"identifier":"retried-image","name":"Retried Image","url":"https://cdn.example.test/retried.jpg","conversions":{},"conversionsInProgress":[],"translations":{},"created":"2026-01-04T00:00:00+00:00","updated":"2026-01-04T00:00:00+00:00"}}]}
            JSON,
        ),
    );

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync')
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    $media = Media::query()->find(301);

    expect($media)->not->toBeNull();
    expect($media?->identifier)->toBe('retried-image');
});

it('fails with a resumable message when api requests keep throwing exceptions', function () {
    $client = mock(ClientContract::class);

    $client
        ->shouldReceive('request')
        ->times(4)
        ->andThrow(new RuntimeException('Forced API failure for testing.'));

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new Media()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);
    $repo->shouldReceive('getBlueprint')->andReturn('media');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$repo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync')
        ->expectsOutputToContain(
            'Sync interrupted. You can resume this session with --resume once the issue is resolved.',
        )
        ->expectsOutputToContain('RuntimeException: Forced API failure for testing.')
        ->assertExitCode(1);

    $lockName = 'typdy:eloquent:sync:team-test:project-test:*';

    expect($this->privateStoragePath . '/sync/' . $lockName . '.json')->toBeFile();
});
