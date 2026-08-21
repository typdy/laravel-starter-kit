<?php

declare(strict_types=1);

use Typdy\StarterKit\Containers\Contracts\Container;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Webhooks\Support\NonDatabaseCacheInvalidator;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Storage\Contracts\DatabaseDriver;
use Typdy\StarterKit\Storage\Contracts\Driver;
use Typdy\StarterKit\Storage\Contracts\InvalidatesRequests;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Typdy;

uses(TestCase::class);

beforeEach(function () {
    $this->container = mock(Container::class);

    Typdy::$container = $this->container;
});

afterEach(function () {
    Typdy::$container = null;
});

it('invalidates non-database driver cache requests for database-backed repositories', function () {
    $dbDriver = new class implements DatabaseDriver {
        public function all(Collection $repository, Request $request): ?iterable
        {
            return null;
        }

        public function delete(Collection $repository, Request $request): void {}

        public function find(Collection $repository, Request $request): ?Construct
        {
            return null;
        }

        public function getMetadata(Collection $repository, Request $request): Metadata
        {
            return new Metadata();
        }

        public function getReplayableRequests(Collection $repository, ?int $constructId): array
        {
            return [];
        }

        public function sync(
            Collection $repository,
            Request $request,
            Construct|iterable $data,
            array $meta = [],
        ): Construct|iterable|null {
            return null;
        }
    };

    $nonDbDriver = new class implements Driver, InvalidatesRequests {
        public int $invalidated = 0;

        public function all(Collection $repository, Request $request): ?iterable
        {
            return null;
        }

        public function delete(Collection $repository, Request $request): void {}

        public function find(Collection $repository, Request $request): ?Construct
        {
            return null;
        }

        public function getMetadata(Collection $repository, Request $request): Metadata
        {
            return new Metadata();
        }

        public function getReplayableRequests(Collection $repository, ?int $constructId): array
        {
            return [];
        }

        public function invalidate(Collection $repository, Request $request): void
        {
            $this->invalidated++;
        }

        public function sync(
            Collection $repository,
            Request $request,
            Construct|iterable $data,
            array $meta = [],
        ): Construct|iterable|null {
            return null;
        }
    };

    $requests = [
        new Request(team: 'team', project: 'project', blueprint: 'article', id: 1),
        new Request(team: 'team', project: 'project', blueprint: 'article', id: 2),
    ];

    $repo = new class($dbDriver::class, $nonDbDriver::class, $requests) implements Collection, Replayable {
        /**
         * @param list<Request> $requests
         */
        public function __construct(
            private readonly string $dbDriver,
            private readonly string $nonDbDriver,
            private readonly array $requests,
        ) {}

        public function getBlueprint(): string
        {
            return 'article';
        }

        public function getDrivers(): array
        {
            return [$this->nonDbDriver, $this->dbDriver];
        }

        public function getProject(): string
        {
            return 'project';
        }

        public function getSignature(): string
        {
            return 'team:project:article';
        }

        public function getTeam(): string
        {
            return 'team';
        }

        public function isGlobal(): bool
        {
            return false;
        }

        public function replay(?int $constructId = null, array $options = []): int
        {
            return count($this->requests);
        }

        /**
         * @return list<Request>
         */
        public function replayableRequests(?int $constructId = null): array
        {
            return $this->requests;
        }
    };

    $resolver = mock(ResolvesRepositories::class);
    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team', 'project')
        ->andReturn([$repo]);

    $this->container
        ->shouldReceive('make')
        ->with(ResolvesRepositories::class, [])
        ->once()
        ->andReturn($resolver);

    $this->container
        ->shouldReceive('make')
        ->with($nonDbDriver::class, [])
        ->once()
        ->andReturn($nonDbDriver);

    $invalidator = new NonDatabaseCacheInvalidator();

    $invalidator->invalidate('team', 'project', 'constructs', 'article', null);

    expect($nonDbDriver->invalidated)->toBe(2);
});

it('skips invalidation when repository does not support replayable request discovery', function () {
    $dbDriver = new class implements DatabaseDriver {
        public function all(Collection $repository, Request $request): ?iterable
        {
            return null;
        }

        public function delete(Collection $repository, Request $request): void {}

        public function find(Collection $repository, Request $request): ?Construct
        {
            return null;
        }

        public function getMetadata(Collection $repository, Request $request): Metadata
        {
            return new Metadata();
        }

        public function getReplayableRequests(Collection $repository, ?int $constructId): array
        {
            return [];
        }

        public function sync(
            Collection $repository,
            Request $request,
            Construct|iterable $data,
            array $meta = [],
        ): Construct|iterable|null {
            return null;
        }
    };

    $nonDbDriver = new class implements Driver, InvalidatesRequests {
        public int $invalidated = 0;

        public function all(Collection $repository, Request $request): ?iterable
        {
            return null;
        }

        public function delete(Collection $repository, Request $request): void {}

        public function find(Collection $repository, Request $request): ?Construct
        {
            return null;
        }

        public function getMetadata(Collection $repository, Request $request): Metadata
        {
            return new Metadata();
        }

        public function getReplayableRequests(Collection $repository, ?int $constructId): array
        {
            return [];
        }

        public function invalidate(Collection $repository, Request $request): void
        {
            $this->invalidated++;
        }

        public function sync(
            Collection $repository,
            Request $request,
            Construct|iterable $data,
            array $meta = [],
        ): Construct|iterable|null {
            return null;
        }
    };

    $repo = new class($dbDriver::class, $nonDbDriver::class) implements Collection {
        public function __construct(
            private readonly string $dbDriver,
            private readonly string $nonDbDriver,
        ) {}

        public function getBlueprint(): string
        {
            return 'article';
        }

        public function getDrivers(): array
        {
            return [$this->nonDbDriver, $this->dbDriver];
        }

        public function getProject(): string
        {
            return 'project';
        }

        public function getSignature(): string
        {
            return 'team:project:article';
        }

        public function getTeam(): string
        {
            return 'team';
        }

        public function isGlobal(): bool
        {
            return false;
        }
    };

    $resolver = mock(ResolvesRepositories::class);
    $resolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team', 'project')
        ->andReturn([$repo]);

    $this->container
        ->shouldReceive('make')
        ->with(ResolvesRepositories::class, [])
        ->once()
        ->andReturn($resolver);

    $this->container
        ->shouldNotReceive('make')
        ->with($nonDbDriver::class, []);

    $invalidator = new NonDatabaseCacheInvalidator();

    $invalidator->invalidate('team', 'project', 'constructs', 'article', null);

    expect($nonDbDriver->invalidated)->toBe(0);
});
