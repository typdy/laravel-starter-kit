<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Typdy\StarterKit\Laravel\Jobs\RunEloquentSyncJob;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Webhooks\Handlers\EloquentSyncHandler;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;

uses(TestCase::class);

it('dispatches a configured scoped sync job for supported webhook events', function () {
    Bus::fake();

    $handler = new EloquentSyncHandler()->withOptions([
        'team' => 'team-option',
        'project' => 'project-option',
        'tries' => 7,
        'backoff' => [5, 25, 55],
        'delay' => 12,
    ]);

    $body = '{"event":"update","domain":"constructs","blueprint":{"identifier":"article"}}';

    // @mago-expect lint:no-literal-password
    $secret = 'webhook-secret';

    $payload = Payload::make(
        'default',
        $secret,
        $body,
        ['Signature' => hash_hmac('sha256', $body, $secret)],
    );

    $results = new ResultSet();

    $handler->handle($payload, $results);

    Bus::assertDispatched(RunEloquentSyncJob::class, function (RunEloquentSyncJob $job): bool {
        expect($job->options)->toBe([
            '--team' => 'team-option',
            '--project' => 'project-option',
            '--blueprint' => 'article',
        ]);

        expect($job->domain)->toBe('constructs');
        expect($job->constructId)->toBeNull();

        expect($job->tries)->toBe(7);
        expect($job->backoff)->toBe([5, 25, 55]);

        return true;
    });

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeFalse();
    expect($results->results[0]->message)->toContain('Queued eloquent sync');
});

it('forwards construct id when provided in a supported webhook payload', function () {
    Bus::fake();

    $handler = new EloquentSyncHandler()->withOptions([
        'team' => 'team-option',
        'project' => 'project-option',
    ]);

    $body = '{"event":"update","domain":"constructs","construct":{"id":42},"blueprint":{"identifier":"article"}}';

    // @mago-expect lint:no-literal-password
    $secret = 'webhook-secret';

    $payload = Payload::make(
        'default',
        $secret,
        $body,
        ['Signature' => hash_hmac('sha256', $body, $secret)],
    );

    $results = new ResultSet();

    $handler->handle($payload, $results);

    Bus::assertDispatched(RunEloquentSyncJob::class, function (RunEloquentSyncJob $job): bool {
        expect($job->constructId)->toBe(42);

        return true;
    });

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeFalse();
});

it('does not dispatch a sync job for unsupported webhook events', function () {
    Bus::fake();

    $handler = new EloquentSyncHandler()->withOptions([
        'team' => 'team-option',
        'project' => 'project-option',
    ]);

    $body = '{"event":"create","domain":"languages","blueprint":{"identifier":"article"}}';

    // @mago-expect lint:no-literal-password
    $secret = 'webhook-secret';

    $payload = Payload::make(
        'default',
        $secret,
        $body,
        ['Signature' => hash_hmac('sha256', $body, $secret)],
    );

    $results = new ResultSet();

    $handler->handle($payload, $results);

    Bus::assertNotDispatched(RunEloquentSyncJob::class);

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeFalse();
    expect($results->results[0]->message)->toContain('No action taken for event');
});

it('fails when blueprint is missing for a supported event', function () {
    Bus::fake();

    $handler = new EloquentSyncHandler()->withOptions([
        'team' => 'team-option',
        'project' => 'project-option',
    ]);

    $body = '{"event":"update","domain":"constructs"}';

    // @mago-expect lint:no-literal-password
    $secret = 'webhook-secret';

    $payload = Payload::make(
        'default',
        $secret,
        $body,
        ['Signature' => hash_hmac('sha256', $body, $secret)],
    );

    $results = new ResultSet();

    $handler->handle($payload, $results);

    Bus::assertNotDispatched(RunEloquentSyncJob::class);

    expect($results->results)->toHaveCount(1);
    expect($results->results[0]->failed)->toBeTrue();
    expect($results->results[0]->message)->toContain('did not include a blueprint identifier');
});
