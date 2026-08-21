<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Controllers\WebhooksController;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Webhooks\Fixtures\ConfigurableTestHandler;
use Typdy\StarterKit\Laravel\Tests\Unit\Webhooks\Fixtures\NotAHandler;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\WebhookCoordinator;

uses(TestCase::class);

it('registers configured handlers and passes merged options from webhook config', function () {
    $coordinator = new WebhookCoordinator();
    $controller = new WebhooksController($coordinator);

    $method = new ReflectionMethod(WebhooksController::class, 'registerHandlersForWebhook');
    $method->setAccessible(true);

    $method->invoke(
        $controller,
        'default',
        [
            ConfigurableTestHandler::class => ['tries' => 3, 'delay' => 9],
        ],
        'team-alpha',
        'project-alpha',
    );

    $body = '{"event":"update","domain":"constructs","blueprint":{"identifier":"article"}}';

    // @mago-expect lint:no-literal-password
    $secret = 'webhook-secret';

    $payload = Payload::make(
        'default',
        $secret,
        $body,
        ['Signature' => hash_hmac('sha256', $body, $secret)],
    );

    $results = $coordinator->handle($payload);

    $result = $results['default']['configurable-test-handler']->results[0] ?? null;

    expect($result)->not->toBeNull();

    /** @var array<string, mixed>|null $decoded */
    $decoded = json_decode($result->message ?? '', associative: true);

    expect($decoded)->toBe([
        'tries' => 3,
        'delay' => 9,
        'team' => 'team-alpha',
        'project' => 'project-alpha',
    ]);
});

it('throws when a configured webhook handler does not implement the handler contract', function () {
    $coordinator = new WebhookCoordinator();
    $controller = new WebhooksController($coordinator);

    $method = new ReflectionMethod(WebhooksController::class, 'registerHandlersForWebhook');
    $method->setAccessible(true);

    $method->invoke(
        $controller,
        'default',
        [
            NotAHandler::class,
        ],
        'team-alpha',
        'project-alpha',
    );
})->throws(
    RuntimeException::class,
    "Webhook handler 'Typdy\\StarterKit\\Laravel\\Tests\\Unit\\Webhooks\\Fixtures\\NotAHandler' must implement the Handler interface.",
);
