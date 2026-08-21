<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console\Concerns;

use Illuminate\Database\Eloquent\Model;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

/**
 * @api
 */
trait HasModelResolution
{
    private ResolvesModels $modelResolver;

    /**
     * @var list<Construct&Model>
     */
    private array $resolvedModels = [];

    /**
     * @return list{string, string, ?string}
     */
    abstract private function getScope(): array;

    /**
     * @return list<string>
     */
    private function resolveModelBlueprints(): array
    {
        return array_map(
            static fn (Construct $model): string => $model->getBlueprint(),
            $this->resolveModels(),
        )
            |> array_unique(...)
            |> array_values(...);
    }

    /**
     * @return list<Construct&Model>
     */
    private function resolveModels(): array
    {
        if ($this->resolvedModels !== []) {
            return $this->resolvedModels;
        }

        [$team, $project, $blueprint] = $this->getScope();

        $models = [];

        if ($blueprint === null) {
            $models = $this->modelResolver->resolveMany($team, $project);
        } else {
            $model = $this->modelResolver->resolveOne($team, $project, $blueprint);
            $models = $model !== null ? [$model] : [];
        }

        // @mago-expect analysis:invalid-property-assignment-value not though
        $this->resolvedModels = array_filter(
            $models,
            static fn (Construct $model): bool => $model instanceof Model,
        )
            |> array_values(...);

        return $this->resolvedModels;
    }
}
