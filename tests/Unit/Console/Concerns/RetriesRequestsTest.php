<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Typdy\StarterKit\Api\Contracts\Client;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures\ClassWithRetriesRequests;
use Typdy\StarterKit\Parsers\Contracts\ResponseParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\TypdyConfig;

uses(TestCase::class);

it('retries retryable statuses and eventually returns the successful document', function () {
    $client = mock(Client::class);
    $parser = mock(ResponseParser::class);
    $container = mock(Container::class);

    $client
        ->shouldReceive('request')
        ->times(3)
        ->andReturn(new Response(500), new Response(429), new Response(200));

    $parser
        ->shouldReceive('parse')
        ->times(3)
        ->andReturnUsing(static fn ($response): Document => new Document($response));

    $container
        ->shouldReceive('make')
        ->times(3)
        ->with(Client::class)
        ->andReturn($client);

    $container
        ->shouldReceive('make')
        ->times(3)
        ->with(ResponseParser::class, ['mixedType' => true])
        ->andReturn($parser);

    $api = new RequestCoordinator(
        team: 'team-a',
        project: 'project-a',
        container: $container,
        config: new TypdyConfig(team: 'team-a', project: 'project-a'),
    );

    $harness = new ClassWithRetriesRequests();
    $harness->setApi($api);
    $harness->configureRetries(maxRetries: 2, retryDelay: 0);

    $document = $harness->request('/entries', ['page' => 1]);

    expect($document->response->getStatusCode())->toBe(200);
});

it('returns immediately for a non-retryable status', function () {
    $client = mock(Client::class);
    $parser = mock(ResponseParser::class);
    $container = mock(Container::class);

    $client
        ->shouldReceive('request')
        ->once()
        ->andReturn(new Response(404));

    $parser
        ->shouldReceive('parse')
        ->once()
        ->andReturnUsing(static fn ($response): Document => new Document($response));

    $container
        ->shouldReceive('make')
        ->once()
        ->with(Client::class)
        ->andReturn($client);

    $container
        ->shouldReceive('make')
        ->once()
        ->with(ResponseParser::class, ['mixedType' => true])
        ->andReturn($parser);

    $api = new RequestCoordinator(
        team: 'team-b',
        project: 'project-b',
        container: $container,
        config: new TypdyConfig(team: 'team-b', project: 'project-b'),
    );

    $harness = new ClassWithRetriesRequests();
    $harness->setApi($api);
    $harness->configureRetries(maxRetries: 2, retryDelay: 0);

    $document = $harness->request('/entries');

    expect($document->response->getStatusCode())->toBe(404);
});

it('throws the final exception when all attempts fail', function () {
    $client = mock(Client::class);
    $container = mock(Container::class);

    $client
        ->shouldReceive('request')
        ->times(3)
        ->andThrow(new RuntimeException('network down'));

    $container
        ->shouldReceive('make')
        ->times(3)
        ->with(Client::class)
        ->andReturn($client);

    $api = new RequestCoordinator(
        team: 'team-c',
        project: 'project-c',
        container: $container,
        config: new TypdyConfig(team: 'team-c', project: 'project-c'),
    );

    $harness = new ClassWithRetriesRequests();
    $harness->setApi($api);
    $harness->configureRetries(maxRetries: 2, retryDelay: 0);

    expect(fn (): Document => $harness->request('/entries'))
        ->toThrow(RuntimeException::class, 'network down');
});
