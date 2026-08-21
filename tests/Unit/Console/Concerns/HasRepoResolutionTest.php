<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures\ClassWithRepoResolution;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;

uses(TestCase::class);

it('resolves many repositories when a blueprint is not provided', function () {
    $resolver = mock(ResolvesRepositories::class);

    $first = mock(Collection::class);
    $second = mock(Collection::class);

    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-a', 'project-a')
        ->andReturn([$first, $second]);

    $resolver->shouldNotReceive('resolveOne');

    $harness = new ClassWithRepoResolution('team-a', 'project-a', null);
    $harness->setResolver($resolver);

    $resolved = $harness->resolveReposForTest();

    expect($resolved)->toHaveCount(2);
    expect($resolved[0])->toBe($first);
    expect($resolved[1])->toBe($second);
});

it('resolves a single repository when blueprint is provided', function () {
    $resolver = mock(ResolvesRepositories::class);

    $repo = mock(Collection::class);

    $resolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-b', 'project-b', 'articles')
        ->andReturn($repo);

    $resolver->shouldNotReceive('resolveMany');

    $harness = new ClassWithRepoResolution('team-b', 'project-b', 'articles');
    $harness->setResolver($resolver);

    expect($harness->resolveReposForTest())->toBe([$repo]);
});

it('returns an empty list when single repository resolution misses', function () {
    $resolver = mock(ResolvesRepositories::class);

    $resolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-c', 'project-c', 'missing')
        ->andReturn(null);

    $harness = new ClassWithRepoResolution('team-c', 'project-c', 'missing');
    $harness->setResolver($resolver);

    expect($harness->resolveReposForTest())->toBeEmpty();
});

it('filters out global repositories when excludeGlobal is true', function () {
    $resolver = mock(ResolvesRepositories::class);

    $global = mock(Collection::class);
    $nonGlobal = mock(Collection::class);

    $global->shouldReceive('isGlobal')->once()->andReturn(true);
    $nonGlobal->shouldReceive('isGlobal')->once()->andReturn(false);

    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-d', 'project-d')
        ->andReturn([$global, $nonGlobal]);

    $harness = new ClassWithRepoResolution('team-d', 'project-d', null);
    $harness->setResolver($resolver);

    expect($harness->resolveReposForTest(excludeGlobal: true))->toBe([$nonGlobal]);
});
