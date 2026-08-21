<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Typdy\StarterKit\Laravel\Console\EloquentSync;
use Typdy\StarterKit\Laravel\Jobs\RunEloquentSyncJob;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Webhooks\Contracts\InvalidatesNonDatabaseCache;

uses(TestCase::class);

it('completes without requeue when sync command succeeds', function () {
    $artisan = mock(ConsoleKernel::class);

    $options = [
        '--team' => 'team-test',
        '--project' => 'project-test',
        '--blueprint' => 'article',
    ];

    $artisan
        ->shouldReceive('call')
        ->once()
        ->with('typdy:eloquent:sync', [
            '--team' => 'team-test',
            '--project' => 'project-test',
            '--blueprint' => 'article',
            '--id' => 42,
        ])
        ->andReturn(0);

    $job = new RunEloquentSyncJob($options, 42, 'constructs');

    $invalidator = mock(InvalidatesNonDatabaseCache::class);
    $invalidator
        ->shouldReceive('invalidate')
        ->once()
        ->with('team-test', 'project-test', 'constructs', 'article', 42);

    $queueJob = mock(QueueJob::class);
    $queueJob->shouldNotReceive('release');

    $job->setJob($queueJob);
    $job->handle($artisan, $invalidator);
});

it('releases with backoff when sync command returns lock-busy exit code', function () {
    $artisan = mock(ConsoleKernel::class);

    $options = [
        '--team' => 'team-test',
        '--project' => 'project-test',
        '--blueprint' => 'article',
    ];

    $artisan
        ->shouldReceive('call')
        ->once()
        ->with('typdy:eloquent:sync', $options)
        ->andReturn(EloquentSync::EXIT_LOCK_BUSY);

    $job = new RunEloquentSyncJob($options);

    $invalidator = mock(InvalidatesNonDatabaseCache::class);
    $invalidator->shouldNotReceive('invalidate');

    $queueJob = mock(QueueJob::class);
    $queueJob->shouldReceive('attempts')->once()->andReturn(2);
    $queueJob->shouldReceive('release')->once()->with(30);

    $job->setJob($queueJob);
    $job->handle($artisan, $invalidator);
});

it('throws when webhook sync options do not include a blueprint', function () {
    $artisan = mock(ConsoleKernel::class);
    $artisan->shouldNotReceive('call');

    $job = new RunEloquentSyncJob(['--team' => 'team-test', '--project' => 'project-test']);

    $invalidator = mock(InvalidatesNonDatabaseCache::class);
    $invalidator->shouldNotReceive('invalidate');

    $job->handle($artisan, $invalidator);
})->throws(RuntimeException::class, 'Webhook sync job requires a --blueprint option.');

it('throws when sync fails for a non-lock reason', function () {
    $artisan = mock(ConsoleKernel::class);

    $options = [
        '--team' => 'team-test',
        '--project' => 'project-test',
        '--blueprint' => 'article',
    ];

    $artisan
        ->shouldReceive('call')
        ->once()
        ->with('typdy:eloquent:sync', $options)
        ->andReturn(1);

    $artisan
        ->shouldReceive('output')
        ->once()
        ->andReturn('RuntimeException: API unreachable');

    $job = new RunEloquentSyncJob($options);

    $invalidator = mock(InvalidatesNonDatabaseCache::class);
    $invalidator->shouldNotReceive('invalidate');

    $job->handle($artisan, $invalidator);
})->throws(RuntimeException::class, 'Eloquent sync job failed: RuntimeException: API unreachable');
