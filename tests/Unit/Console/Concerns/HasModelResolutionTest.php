<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Models\Media;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures\ClassWithModelResolution;
use Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures\FakeConstruct;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;

uses(TestCase::class);

it('resolves many models when a blueprint is not provided', function () {
    $resolver = mock(ResolvesModels::class);

    $first = new Media();
    $second = new Media();

    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-a', 'project-a')
        ->andReturn([$first, $second]);

    $resolver->shouldNotReceive('resolveOne');

    $harness = new ClassWithModelResolution('team-a', 'project-a', null);
    $harness->setResolver($resolver);

    $resolved = $harness->resolveModelsForTest();

    expect($resolved)->toHaveCount(2);
    expect($resolved[0])->toBe($first);
    expect($resolved[1])->toBe($second);
    expect($harness->resolveModelBlueprintsForTest())->toBe(['media']);
});

it('resolves a single model when blueprint is provided', function () {
    $resolver = mock(ResolvesModels::class);

    $model = new Media();

    $resolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-b', 'project-b', 'media')
        ->andReturn($model);

    $resolver->shouldNotReceive('resolveMany');

    $harness = new ClassWithModelResolution('team-b', 'project-b', 'media');
    $harness->setResolver($resolver);

    expect($harness->resolveModelsForTest())->toBe([$model]);
    expect($harness->resolveModelBlueprintsForTest())->toBe(['media']);
});

it('returns an empty list when single model resolution misses', function () {
    $resolver = mock(ResolvesModels::class);

    $resolver
        ->shouldReceive('resolveOne')
        ->twice() // missing so resolution cache is missed
        ->with('team-c', 'project-c', 'missing')
        ->andReturn(null);

    $harness = new ClassWithModelResolution('team-c', 'project-c', 'missing');
    $harness->setResolver($resolver);

    expect($harness->resolveModelsForTest())->toBeEmpty();
    expect($harness->resolveModelBlueprintsForTest())->toBeEmpty();
});

it('filters out resolved constructs that are not eloquent models', function () {
    $resolver = mock(ResolvesModels::class);

    $constructOnly = new FakeConstruct();
    $eloquentModel = new Media();

    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-d', 'project-d')
        ->andReturn([$constructOnly, $eloquentModel]);

    $harness = new ClassWithModelResolution('team-d', 'project-d', null);
    $harness->setResolver($resolver);

    expect($harness->resolveModelsForTest())->toBe([$eloquentModel]);
});

it('caches resolved models and only hits the resolver once', function () {
    $resolver = mock(ResolvesModels::class);

    $model = new Media();

    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-e', 'project-e')
        ->andReturn([$model]);

    $harness = new ClassWithModelResolution('team-e', 'project-e', null);
    $harness->setResolver($resolver);

    $first = $harness->resolveModelsForTest();
    $second = $harness->resolveModelsForTest();

    expect($first)->toBe([$model]);
    expect($second)->toBe([$model]);
});
