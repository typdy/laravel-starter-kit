<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Migrations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Typdy\StarterKit\Laravel\Support\Migrations\Data\MigrationPlanData;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Contracts\Relatable;

use function array_filter;
use function array_key_exists;
use function in_array;
use function method_exists;

final readonly class RelationshipPlanner
{
    /**
     * @param array<int, Construct&Model> $models
     * @param array<string, array<int, string>> $existing
     *
     * @return array<int, MigrationPlanData>
     */
    public function plan(array $models, array $existing): array
    {
        $plans = [];
        $pivots = [];

        foreach ($models as $model) {
            $table = $model->getTable();

            $relationships = $this->resolveModelRelationships($model);

            foreach ($relationships as $relation) {
                // we should only generate migrations for typdy relationships
                if (!$relation->getRelated() instanceof Construct) {
                    continue;
                }

                // to-one construct relationship
                if ($relation instanceof BelongsTo) {
                    $plans[] = $this->makeBelongsToPlan($relation, $table, $existing);
                }

                // to-many constructs relationship
                if ($relation instanceof BelongsToMany) {
                    $plans[] = $this->makeBelongsToManyPlan($relation, $table, $existing, $pivots);
                }
            }
        }

        return array_filter($plans);
    }

    /**
     * @param array<string, array<int, string>> $existing
     */
    private function columnExists(string $table, string $column, array $existing): bool
    {
        return in_array($column, $existing[$table] ?? [], strict: true);
    }

    /**
     * @param array<string, array<int, string>> $existing
     * @param array<int, string> $pivots
     */
    private function makeBelongsToManyPlan(
        BelongsToMany $relation,
        string $table,
        array $existing,
        array &$pivots,
    ): ?MigrationPlanData {
        $pivot = $relation->getTable();

        if ($this->pivotExists($pivot, $pivots, $existing)) {
            return null;
        }

        $pivots[] = $pivot;

        $foreignKey = $relation->getForeignPivotKeyName();
        $relatedKey = $relation->getRelatedPivotKeyName();
        $relatedTable = $relation->getRelated()->getTable();

        return new MigrationPlanData(
            table: $pivot,
            create: true,
            lines: [
                "\$table->foreignId('{$foreignKey}')->constrained('{$table}')->cascadeOnDelete();",
                "\$table->foreignId('{$relatedKey}')->constrained('{$relatedTable}')->cascadeOnDelete();",
                "\$table->unique(['{$foreignKey}', '{$relatedKey}']);",
            ],
            name: "create_{$pivot}_table",
            stub: 'table-migration',
        );
    }

    /**
     * @param array<string, array<int, string>> $existing
     */
    private function makeBelongsToPlan(BelongsTo $relation, string $table, array $existing): ?MigrationPlanData
    {
        $foreignTable = $relation->getRelated()->getTable();
        $foreignKey = $relation->getForeignKeyName();

        if ($this->columnExists($table, $foreignKey, $existing)) {
            return null;
        }

        return new MigrationPlanData(
            table: $table,
            create: false,
            lines: [
                "\$table->foreignId('{$foreignKey}')->nullable()->constrained('{$foreignTable}')->nullOnDelete();",
            ],
            name: "add_{$foreignKey}_to_{$table}_table",
        );
    }

    /**
     * @param array<string, array<int, string>> $existing
     * @param array<int, string> $pivots
     */
    private function pivotExists(string $pivot, array $pivots, array $existing): bool
    {
        return in_array($pivot, $pivots, strict: true) || array_key_exists($pivot, $existing);
    }

    /**
     * @param Construct&Model $model
     *
     * @return array<int, Relation>
     */
    private function resolveModelRelationships(Model $model): array
    {
        if (!$model instanceof Relatable) {
            return [];
        }

        $relationships = $model->getRelationships();

        $resolved = [];

        foreach ($relationships as $method) {
            if (!method_exists($model, $method)) {
                continue;
            }

            // @mago-expect analysis:string-member-selector
            // @mago-expect analysis:mixed-assignment checked below
            $relation = $model->{$method}();

            if (!$relation instanceof Relation) {
                continue;
            }

            $resolved[] = $relation;
        }

        return $resolved;
    }
}
