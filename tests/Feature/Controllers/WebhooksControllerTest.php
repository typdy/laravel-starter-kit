<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Tests\Feature\Controllers\Fixtures\DummyWebhookHandler;
use Typdy\StarterKit\Laravel\Tests\TestCase;

uses(TestCase::class);

it('returns a failed response when the webhook is not configured', function () {
    $response = $this->postJson('/typdy/webhooks/not-configured');

    $response->assertOk();

    $response->assertExactJson([
        'status' => 'failed',
        'results' => [
            'status' => 'failed',
            'message' => "Webhook 'not-configured' is not configured.",
        ],
    ]);
});

it('returns a failed response when the webhook signature is invalid', function () {
    config()->set('typdy.webhooks', [
        'default' => [
            'team' => 'team-test',
            'project' => 'project-test',
            // @mago-expect lint:no-literal-password
            'secret' => 'valid-secret',
            'handlers' => [
                DummyWebhookHandler::class,
            ],
        ],
    ]);

    $body = '{"event":"update","domain":"constructs"}';

    $response = $this->call(
        method: 'POST',
        uri: '/typdy/webhooks/default',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SIGNATURE' => 'invalid-signature',
        ],
        content: $body,
    );

    $response->assertOk();

    $response->assertExactJson([
        'status' => 'failed',
        'results' => [
            'status' => 'failed',
            'message' => 'Invalid signing key.',
        ],
    ]);
});

it('returns a success response when the webhook signature is valid and a handler runs', function () {
    config()->set('typdy.webhooks', [
        'default' => [
            'team' => 'team-test',
            'project' => 'project-test',
            // @mago-expect lint:no-literal-password
            'secret' => 'valid-secret',
            'handlers' => [
                DummyWebhookHandler::class,
            ],
        ],
    ]);

    $body = '{"event":"update","domain":"constructs"}';

    $signature = hash_hmac('sha256', $body, key: 'valid-secret');

    $response = $this->call(
        method: 'POST',
        uri: '/typdy/webhooks/default',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SIGNATURE' => $signature,
        ],
        content: $body,
    );

    $response->assertOk();

    $response->assertExactJson([
        'status' => 'success',
        'results' => [
            [
                'webhook' => 'default',
                'handler' => 'dummy-webhook-handler',
                'status' => 'success',
                'message' => 'Handled default',
            ],
        ],
    ]);
});
