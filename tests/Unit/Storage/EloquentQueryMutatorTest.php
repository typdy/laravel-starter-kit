<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Typdy\StarterKit\Laravel\Storage\Mutators\EloquentQueryMutator;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Models\Fixtures\TypdyModel;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;

uses(TestCase::class);

it('mutates db closure queries into a signature', function () {
    $resolver = mock(ResolvesModels::class);

    $resolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'article')
        ->andReturn(new TypdyModel());

    $mutator = new EloquentQueryMutator($resolver);

    $request = new Request(
        team: 'team-test',
        project: 'project-test',
        blueprint: 'article',
        query: [
            'db' => static fn (Builder $query) => $query
                ->where('title', 'Hello World')
                ->where('id', '>', 10),
            'parameters' => ['all' => true],
        ],
    );

    $mutated = $mutator->mutate($request);

    expect($mutated)->not->toBe($request);
    expect($mutated->query)->toHaveKey('db_signature');
    expect($mutated->query)->not->toHaveKey('db');

    /** @var array{sql: string, bindings: array<int, mixed>, connection: string|null, grammar: string} $signature */
    $signature = $mutated->query['db_signature'];

    expect($signature['sql'])->toContain('where');
    expect($signature['sql'])->toContain('team');
    expect($signature['sql'])->toContain('project');
    expect($signature['sql'])->toContain('title');
    expect($signature['bindings'])->toBe(['team-test', 'project-test', 'Hello World', 10]);
    expect($signature['grammar'])->toBeString();
    expect($signature['grammar'])->not->toBeEmpty();
});

it('returns the request unchanged when no db closure is provided', function () {
    $resolver = mock(ResolvesModels::class);
    $resolver->shouldNotReceive('resolveOne');

    $mutator = new EloquentQueryMutator($resolver);

    $request = new Request(
        team: 'team-test',
        project: 'project-test',
        blueprint: 'article',
        query: ['parameters' => ['all' => true]],
    );

    $mutated = $mutator->mutate($request);

    expect($mutated)->toBe($request);
    expect($mutated->query)->toBe($request->query);
});
