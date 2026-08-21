<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Support\Sync\Data\IncludeDiscoveryData;
use Typdy\StarterKit\Laravel\Support\Sync\FetchPlanner;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Fixtures\AlphaModel;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Fixtures\ArticleModel;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Fixtures\BetaModel;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Fixtures\CategoryModel;
use Typdy\StarterKit\Repositories\Contracts\Collection;

uses(TestCase::class);

it('builds global, initial and deferred fetch tasks from repos and includes', function () {
    $makeRepo = function (string $blueprint, bool $isGlobal): Collection {
        $repo = mock(Collection::class);
        $repo->shouldReceive('getBlueprint')->andReturn($blueprint);
        $repo->shouldReceive('isGlobal')->andReturn($isGlobal);

        return $repo;
    };

    $includes = new IncludeDiscoveryData(
        blueprintPaths: [
            'article' => ['category', 'media'],
            'category' => [],
        ],
        deferredBlueprintPaths: [
            'article' => ['category.parent'],
            'category' => [],
        ],
    );

    $plan = new FetchPlanner()->build(
        models: [new ArticleModel(), new CategoryModel()],
        includes: $includes,
        repos: [
            $makeRepo('global', isGlobal: true),
            $makeRepo('article', isGlobal: false),
            $makeRepo('category', isGlobal: false),
            $makeRepo('article', isGlobal: false),
        ],
    );

    expect($plan->global->blueprint)->toBe('global');
    expect($plan->global->path)->toBe('globals');

    expect(array_map(static fn ($task) => $task->blueprint, $plan->initial))->toBe(['article', 'category']);
    expect($plan->initial[0]->parameters)->toBe(['include' => 'category,media']);
    expect($plan->initial[1]->parameters)->toBeEmpty();

    expect(array_map(static fn ($task) => $task->blueprint, $plan->deferred))->toBe(['article', 'category']);
    expect($plan->deferred[0]->parameters)->toBe(['include' => 'category.parent']);
    expect($plan->deferred[1]->parameters)->toBeEmpty();
});

it('falls back to alphabetical ordering when dependency cycles exist', function () {
    $makeRepo = function (string $blueprint): Collection {
        $repo = mock(Collection::class);
        $repo->shouldReceive('getBlueprint')->andReturn($blueprint);
        $repo->shouldReceive('isGlobal')->andReturn(false);

        return $repo;
    };

    $plan = new FetchPlanner()->build(
        models: [new AlphaModel(), new BetaModel()],
        includes: new IncludeDiscoveryData([], []),
        repos: [$makeRepo('beta'), $makeRepo('alpha')],
    );

    expect(array_map(static fn ($task) => $task->blueprint, $plan->initial))->toBe(['alpha', 'beta']);
});
