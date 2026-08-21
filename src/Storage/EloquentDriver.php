<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Storage;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use RuntimeException;
use Typdy\StarterKit\Data\Paginated;
use Typdy\StarterKit\Laravel\Resolvers\ModelResolver;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Storage\Contracts\DatabaseDriver;
use Typdy\StarterKit\Sync\Data\Metadata;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Utils\Signature;

use function array_key_exists;
use function is_array;
use function is_callable;
use function is_iterable;
use function max;

final class EloquentDriver implements DatabaseDriver
{
    public function __construct(
        private ModelResolver $resolver,
    ) {}

    #[Override]
    public function all(Collection $repository, Request $request): ?iterable
    {
        if ($request->blueprint === null) {
            return null;
        }

        $query = $this->newQuery($repository, $request->blueprint);

        if (array_key_exists('db', $request->query) && is_callable($request->query['db'])) {
            $request->query['db']($query);
        }

        if ($request->query['parameters']['all'] ?? true) {
            // @mago-expect analysis:invalid-return-statement
            return $query->get();
        }

        $paginator = $query->paginate(
            perPage: max(1, (int) ($request->query['parameters']['page[size]'] ?? 15)),
            page: max(1, (int) ($request->query['parameters']['page[number]'] ?? 1)),
        );

        /** @var list<Construct> **/
        $models = $paginator->items();

        return new Paginated(
            items: $models,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    #[Override]
    public function delete(Collection $repository, Request $request): void
    {
        if ($request->blueprint === null || $request->id === null) {
            return;
        }

        $this
            ->newQuery($repository, $request->blueprint)
            ->where('id', $request->id)
            ->delete();
    }

    #[Override]
    public function find(Collection $repository, Request $request): ?Construct
    {
        if ($request->blueprint === null || $request->id === null && $request->identifier === null) {
            return null;
        }

        $query = $this->newQuery($repository, $request->blueprint);

        if ($request->id !== null) {
            $query->where('id', $request->id);
        }

        if ($request->identifier !== null) {
            $query->where('identifier', $request->identifier);
        }

        if (array_key_exists('db', $request->query) && is_callable($request->query['db'])) {
            $request->query['db']($query);
        }

        // @mago-expect analysis:invalid-return-statement
        return $query->first();
    }

    #[Override]
    public function getMetadata(Collection $repository, Request $request): Metadata
    {
        if ($request->id !== null || $request->identifier !== null) {
            return new Metadata();
        }

        $map = [
            'page[size]' => 'perPage',
            'page[number]' => 'currentPage',
        ];

        $meta = [];

        foreach ($map as $paramKey => $metaKey) {
            if (!is_array($request->query['parameters'] ?? null)) {
                continue;
            }

            if (!array_key_exists($paramKey, $request->query['parameters'])) {
                continue;
            }

            $meta[$metaKey] = $request->query['parameters'][$paramKey];
        }

        return new Metadata(raw: $meta);
    }

    #[Override]
    public function getReplayableRequests(Collection $repository, ?int $constructId): array
    {
        return [];
    }

    #[Override]
    public function sync(
        Collection $repository,
        Request $request,
        Construct|iterable $data,
        // @mago-expect analysis:unused-parameter
        array $meta = [],
    ): Construct|iterable|null {
        if (is_iterable($data)) {
            foreach ($data as $item) {
                $this->syncOne($repository, $request, $item);
            }

            return Typdy::collect($data);
        }

        return $this->syncOne($repository, $request, $data);
    }

    private function newQuery(Collection $repository, string $blueprint): Builder
    {
        /** @var Construct&Model $model */
        $model = $this->resolver->resolveOne(
            $repository->getTeam(),
            $repository->getProject(),
            $blueprint,
        );

        return $model
            ->newQuery()
            ->where('team', $repository->getTeam())
            ->where('project', $repository->getProject());
    }

    private function syncOne(
        Collection $repository,
        Request $request,
        Construct $model,
    ): Construct {
        Signature::validate($repository, $model);

        if (!$model instanceof Model) {
            throw new RuntimeException('Model must be an instance of Eloquent Model.');
        }

        // @mago-expect analysis:non-existent-property
        $model->team = $repository->getTeam();

        // @mago-expect analysis:non-existent-property
        $model->project = $repository->getProject();

        $model->save();

        return $model;
    }
}
