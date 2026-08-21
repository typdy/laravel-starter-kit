<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Typdy\StarterKit\Laravel\Console\Concerns\HasModelResolution;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;

final class ClassWithModelResolution
{
    use HasModelResolution;

    public function __construct(
        private string $team,
        private string $project,
        private ?string $blueprint,
    ) {}

    /**
     * @return list<string>
     */
    public function resolveModelBlueprintsForTest(): array
    {
        return $this->resolveModelBlueprints();
    }

    /**
     * @return list<Construct&Model>
     */
    public function resolveModelsForTest(): array
    {
        return $this->resolveModels();
    }

    public function setResolver(ResolvesModels $resolver): void
    {
        $this->modelResolver = $resolver;
    }

    /**
     * @return list{string, string, ?string}
     */
    private function getScope(): array
    {
        return [$this->team, $this->project, $this->blueprint];
    }
}
