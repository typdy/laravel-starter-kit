<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Typdy\StarterKit\Data\Paginated;
use Typdy\StarterKit\Laravel\Resolvers\ModelResolver;
use Typdy\StarterKit\Laravel\Storage\EloquentDriver;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures\FakeConstruct;
use Typdy\StarterKit\Laravel\Tests\Unit\Models\Fixtures\TypdyModel;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

uses(TestCase::class);

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'test-team',
        project: 'test-project',
    );

    Schema::dropIfExists('typdy_models');

    Schema::create('typdy_models', function (Blueprint $table) {
        $table->id();
        $table->string('identifier')->nullable();
        $table->string('team')->nullable();
        $table->string('project')->nullable();
        $table->string('title')->nullable();
        $table->json('resource')->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
    });

    $this->repository = mock(Collection::class);
    $this->repository->shouldReceive('getTeam')->andReturn('test-team');
    $this->repository->shouldReceive('getProject')->andReturn('test-project');
    $this->repository->shouldReceive('getBlueprint')->andReturn('article');
    $this->repository->shouldReceive('isGlobal')->andReturnFalse();
    $this->repository->shouldReceive('getDrivers')->andReturn([]);
    $this->repository->shouldReceive('getSignature')->andReturn('test-team:test-project:article');

    $this->makeDriver = function (?callable $assertResolveOne = null): EloquentDriver {
        $resolver = mock(ModelResolver::class);

        $expectation = $resolver
            ->shouldReceive('resolveOne')
            ->andReturnUsing(static fn () => new TypdyModel());

        if ($assertResolveOne !== null) {
            $assertResolveOne($expectation);
        }

        return new EloquentDriver($resolver);
    };
});

afterEach(function () {
    Schema::dropIfExists('typdy_models');
});

it('returns null when all is called without a blueprint', function () {
    $driver = ($this->makeDriver)(static fn ($expectation) => $expectation->never());

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
    );

    expect($driver->all($this->repository, $request))->toBeNull();
});

it('returns all models and applies db query callback', function () {
    DB::table('typdy_models')->insert([
        ['id' => 1, 'identifier' => 'foo', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'Foo'],
        ['id' => 2, 'identifier' => 'bar', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'Bar'],
        [
            'id' => 3,
            'identifier' => 'baz',
            'team' => 'other-team',
            'project' => 'test-project',
            'title' => 'baz',
        ],
    ]);

    $driver = ($this->makeDriver)(
        static fn ($expectation) => $expectation
            ->once()
            ->with('test-team', 'test-project', 'article'),
    );

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
        query: [
            'db' => static fn (Builder $query) => $query->where('identifier', 'bar'),
            'parameters' => ['all' => true],
        ],
    );

    $result = $driver->all($this->repository, $request);

    expect($result)->toBeInstanceOf(EloquentCollection::class);
    expect($result)->toHaveCount(1);
    expect($result->first()?->identifier)->toBe('bar');
});

it('returns paginated data when all parameter is false', function () {
    DB::table('typdy_models')->insert([
        ['id' => 1, 'identifier' => 'first', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'First'],
        ['id' => 2, 'identifier' => 'second', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'Second'],
        ['id' => 3, 'identifier' => 'third', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'Third'],
        ['id' => 4, 'identifier' => 'fourth', 'team' => 'other-team', 'project' => 'test-project', 'title' => 'Fourth'],
    ]);

    $driver = ($this->makeDriver)(
        static fn ($expectation) => $expectation
            ->once()
            ->with('test-team', 'test-project', 'article'),
    );

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
        query: [
            'db' => static fn (Builder $query) => $query->orderBy('id'),
            'parameters' => [
                'all' => false,
                'page[size]' => 2,
                'page[number]' => 2,
            ],
        ],
    );

    $result = $driver->all($this->repository, $request);

    expect($result)->toBeInstanceOf(Paginated::class);
    expect($result->total)->toBe(3);
    expect($result->perPage)->toBe(2);
    expect($result->currentPage)->toBe(2);
    expect($result->lastPage)->toBe(2);
    expect($result->items)->toHaveCount(1);
    expect($result->items[0]->identifier)->toBe('third');
});

it('returns null from find when id and identifier are missing', function () {
    $driver = ($this->makeDriver)(static fn ($expectation) => $expectation->never());

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
    );

    expect($driver->find($this->repository, $request))->toBeNull();
});

it('finds a model by identifier and applies db callback', function () {
    DB::table('typdy_models')->insert([
        [
            'id' => 10,
            'identifier' => 'match',
            'team' => 'test-team',
            'project' => 'test-project',
            'title' => 'Expected',
        ],
        ['id' => 11, 'identifier' => 'match', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'Ignored'],
    ]);

    $driver = ($this->makeDriver)(
        static fn ($expectation) => $expectation
            ->once()
            ->with('test-team', 'test-project', 'article'),
    );

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
        identifier: 'match',
        query: [
            'db' => static fn (Builder $query) => $query->where('title', 'Expected'),
        ],
    );

    $result = $driver->find($this->repository, $request);

    expect($result)->toBeInstanceOf(TypdyModel::class);
    expect($result?->id)->toBe(10);
});

it('deletes the matching row for the repository scope', function () {
    DB::table('typdy_models')->insert([
        ['id' => 21, 'identifier' => 'remove', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'Remove'],
        ['id' => 22, 'identifier' => 'keep', 'team' => 'test-team', 'project' => 'test-project', 'title' => 'Keep'],
    ]);

    $driver = ($this->makeDriver)(
        static fn ($expectation) => $expectation
            ->once()
            ->with('test-team', 'test-project', 'article'),
    );

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
        id: 21,
    );

    $driver->delete($this->repository, $request);

    expect(DB::table('typdy_models')->where('id', 21)->exists())->toBeFalse();
    expect(DB::table('typdy_models')->where('id', 22)->exists())->toBeTrue();
});

it('returns mapped metadata for requests', function () {
    $driver = ($this->makeDriver)(static fn ($expectation) => $expectation->never());

    $singleRequest = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
        id: 1,
    );

    $collectionRequest = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
        query: [
            'parameters' => [
                'page[size]' => 50,
                'page[number]' => 3,
            ],
        ],
    );

    $singleMeta = $driver->getMetadata($this->repository, $singleRequest);
    $collectionMeta = $driver->getMetadata($this->repository, $collectionRequest);

    expect($singleMeta)->toBeInstanceOf(Metadata::class);
    expect($singleMeta->raw)->toBeEmpty();

    expect($collectionMeta->raw)->toBe([
        'perPage' => 50,
        'currentPage' => 3,
    ]);
});

it('syncs a model and auto-scopes by team and project before saving', function () {
    $driver = ($this->makeDriver)(static fn ($expectation) => $expectation->never());

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
    );

    $model = new TypdyModel();
    $model->identifier = 'sync-one';
    $model->title = 'Single';

    $result = $driver->sync($this->repository, $request, $model);

    expect($result)->toBe($model);
    expect(DB::table('typdy_models')->count())->toBe(1);

    $saved = DB::table('typdy_models')->first();

    expect($saved?->team)->toBe('test-team');
    expect($saved?->project)->toBe('test-project');
});

it('syncs iterable data and returns a collected result', function () {
    $driver = ($this->makeDriver)(static fn ($expectation) => $expectation->never());

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'article',
    );

    $first = new TypdyModel();
    $first->identifier = 'sync-a';
    $first->title = 'A';

    $second = new TypdyModel();
    $second->identifier = 'sync-b';
    $second->title = 'B';

    $result = $driver->sync($this->repository, $request, [$first, $second]);

    expect($result)->toBeInstanceOf(SupportCollection::class);
    expect($result)->toHaveCount(2);
    expect(DB::table('typdy_models')->count())->toBe(2);
});

it('throws when syncing a construct that is not an eloquent model', function () {
    $repository = mock(Collection::class);
    $repository->shouldReceive('getSignature')->andReturn('fake-signature');
    $repository->shouldReceive('getTeam')->andReturn('test-team');
    $repository->shouldReceive('getProject')->andReturn('test-project');

    $driver = ($this->makeDriver)(static fn ($expectation) => $expectation->never());

    $request = new Request(
        team: 'test-team',
        project: 'test-project',
        blueprint: 'fake',
    );

    $driver->sync($repository, $request, new FakeConstruct());
})->throws(RuntimeException::class, 'Model must be an instance of Eloquent Model.');
