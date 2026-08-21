<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console\Concerns;

use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;

use function array_filter;
use function array_values;

/**
 * @api
 */
trait HasRepoResolution
{
    private ResolvesRepositories $repoResolver;

    /**
     * @return list{string, string, ?string}
     */
    abstract private function getScope(): array;

    /**
     * @return list<Collection>
     */
    private function resolveRepos(bool $excludeGlobal = false): array
    {
        [$team, $project, $blueprint] = $this->getScope();

        if ($blueprint === null) {
            $repos = $this->repoResolver->resolveMany($team, $project);
        } else {
            $repo = $this->repoResolver->resolveOne($team, $project, $blueprint);

            $repos = $repo !== null ? [$repo] : [];
        }

        if ($excludeGlobal) {
            $repos = array_filter(
                $repos,
                static fn (Collection $repo): bool => !$repo->isGlobal(),
            );
        }

        return $repos |> array_values(...);
    }
}
