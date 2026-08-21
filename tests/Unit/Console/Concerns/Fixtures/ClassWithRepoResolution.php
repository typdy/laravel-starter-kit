<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Typdy\StarterKit\Laravel\Console\Concerns\HasRepoResolution;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;

final class ClassWithRepoResolution
{
    use HasRepoResolution;

    public function __construct(
        private string $team,
        private string $project,
        private ?string $blueprint,
    ) {}

    /**
     * @return list<Construct&Model>
     */
    public function resolveReposForTest(bool $excludeGlobal = false): array
    {
        return $this->resolveRepos($excludeGlobal);
    }

    public function setResolver(ResolvesRepositories $resolver): void
    {
        $this->repoResolver = $resolver;
    }

    private function getScope(): array
    {
        return [$this->team, $this->project, $this->blueprint];
    }
}
