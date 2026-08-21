<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Storage\Mutators;

use Illuminate\Database\Eloquent\Model;
use Override;
use Typdy\StarterKit\Repositories\Data\Request;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Storage\Contracts\RequestMutator;

use function array_key_exists;
use function is_callable;

final readonly class EloquentQueryMutator implements RequestMutator
{
    public function __construct(
        private ResolvesModels $resolver,
    ) {}

    #[Override]
    public function mutate(Request $request): Request
    {
        if ($request->blueprint === null) {
            return $request;
        }

        if (!array_key_exists('db', $request->query) || !is_callable($request->query['db'])) {
            return $request;
        }

        $model = $this->resolver->resolveOne($request->team, $request->project, $request->blueprint);

        if (!$model instanceof Model) {
            return $request;
        }

        $query = $model
            ->newQuery()
            ->where('team', $request->team)
            ->where('project', $request->project);

        $request->query['db']($query);

        $queryData = $request->query;

        unset($queryData['db']);

        $queryData['db_signature'] = [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            // @mago-expect analysis:non-existent-method
            'connection' => $query->getConnection()->getName(),
            // @mago-expect analysis:non-existent-method
            'grammar' => $query->getConnection()->getQueryGrammar()::class,
        ];

        return $request->cloneWithQuery($queryData);
    }
}
