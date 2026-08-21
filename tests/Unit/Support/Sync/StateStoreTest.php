<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Laravel\Support\Sync\StateStore;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Parsers\Contracts\ResponseParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

uses(TestCase::class);

beforeEach(function () {
    $this->storagePath = sys_get_temp_dir() . '/typdy-state-store-tests-' . uniqid('', more_entropy: true);

    if (!is_dir($this->storagePath)) {
        mkdir($this->storagePath, recursive: true);
    }

    Typdy::$config = new TypdyConfig(
        team: 'team-test',
        project: 'project-test',
        privateStoragePath: $this->storagePath,
    );
});

afterEach(function () {
    $deleteDirectory = function (string $path) use (&$deleteDirectory): void {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;

            if (is_dir($entryPath)) {
                $deleteDirectory($entryPath);

                continue;
            }

            unlink($entryPath);
        }

        rmdir($path);
    };

    $deleteDirectory($this->storagePath);

    Typdy::$container = null;
});

it('initializes state and persists it, then restores it in resume mode', function () {
    $store = new StateStore(lockName: 'alpha', resume: false);

    $state = $store->initialize(['article', 'category']);

    expect($state->supportedBlueprints)->toBe(['article', 'category']);
    expect($state->includedConstructs)->toBeEmpty();

    $state->includedConstructs = ['article' => ['1']];
    $store->save($state);

    $resumed = new StateStore(lockName: 'alpha', resume: true)->initialize(['ignored']);

    expect($resumed->runDirectory)->toBe($state->runDirectory);
    expect($resumed->supportedBlueprints)->toBe(['article', 'category']);
    expect($resumed->includedConstructs)->toBe(['article' => ['1']]);
});

it('stores global and page payloads and parses them as documents', function () {
    $store = new StateStore(lockName: 'docs', resume: false);
    $state = $store->initialize(['article']);

    $store->storeGlobalData(new Document(response: new Response(200, body: '{"global": true}')));
    $store->storePageData('article', 1, new Document(response: new Response(200, body: '{"page": 1}')));

    $globalDoc = new Document(response: new Response(200), data: [new Resource(type: 'global', id: 'g')]);
    $pageDoc = new Document(response: new Response(200), data: [new Resource(type: 'article', id: '1')]);

    $parser = mock(ResponseParser::class);
    $parser->shouldReceive('parse')->twice()->andReturn($globalDoc, $pageDoc);

    Typdy::$container = mock(Container::class);
    Typdy::$container
        ->shouldReceive('make')
        ->twice()
        ->with(ResponseParser::class, Mockery::type('array'))
        ->andReturn($parser);

    $documents = $store->getDocuments($state);

    expect($documents)->toHaveCount(2);
    expect($documents[0])->toBe($globalDoc);
    expect($documents[1])->toBe($pageDoc);
});

it('clears state file and run directory', function () {
    $store = new StateStore(lockName: 'cleanup', resume: false);
    $state = $store->initialize(['article']);

    $store->storeGlobalData(new Document(response: new Response(200, body: '{"global": true}')));
    $store->save($state);

    expect($state->runDirectory)->toBeFile();

    $store->clear();

    expect(file_exists($state->runDirectory))->toBeFalse();

    $statePath = Typdy::config()->privateStoragePath . '/sync/cleanup.json';

    expect(file_exists($statePath))->toBeFalse();
});

it('resets invalid persisted state and re-initializes with provided blueprints', function () {
    $base = Typdy::config()->privateStoragePath . '/sync';

    if (!is_dir($base)) {
        mkdir($base, recursive: true);
    }

    file_put_contents($base . '/broken.json', data: 'null');

    $state = new StateStore(lockName: 'broken', resume: true)->initialize(['fresh-blueprint']);

    expect($state->supportedBlueprints)->toBe(['fresh-blueprint']);
    expect($state->includedConstructs)->toBeEmpty();
});
