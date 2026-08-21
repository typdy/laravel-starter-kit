<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Migrations;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Typdy\StarterKit\Laravel\Support\Migrations\Data\MigrationPlanData;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Utils\Str;

use function array_key_exists;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;

final readonly class MigrationPlanner
{
    public function __construct(
        private BlueprintTypeMapper $typeMapper = new BlueprintTypeMapper(),
    ) {}

    /**
     * @param array<int, Construct&Model> $models
     * @param array<string, list<string>> $existing
     *
     * @return array<int, MigrationPlanData>
     */
    public function plan(array $models, array $existing, Document $blueprints): array
    {
        $plans = [];

        $typeIndex = $this->buildBlueprintTypeIndex($blueprints);

        foreach ($models as $model) {
            $table = $model->getTable();
            $fields = $model->getFillable() |> array_unique(...) |> array_values(...);

            $missing = $fields;
            $create = !array_key_exists($table, $existing);

            if (!$create) {
                // @mago-expect analysis:possibly-undefined-string-array-index array_key_exists above
                // @mago-expect analysis:possibly-null-argument nope, we trust $existing param
                $missing = $this->resolveMissingFields($fields, $existing[$table]);
            }

            if ($missing === [] && !$create) {
                continue;
            }

            $lines = [];

            foreach ($missing as $column) {
                $blueprint = $model->getBlueprint();

                if (!array_key_exists($blueprint, $typeIndex)) {
                    continue;
                }

                // if the model defines it, but not the blueprint,
                //  we'll make it nullable for compatibility
                if (!array_key_exists($column, $typeIndex[$blueprint])) {
                    $lines[] = "\$table->json('{$column}')->nullable();";

                    continue;
                }

                $method = $typeIndex[$blueprint][$column];

                $lines[] = "\$table->{$method}('{$column}');";
            }

            $plans[] = new MigrationPlanData($table, $create, $lines);
        }

        return $plans;
    }

    /**
     * @return array<string, array<string, string>>
     *
     * @mago-ignore analysis:all we trust typdy to deliver well-formed blueprint resources
     */
    private function buildBlueprintTypeIndex(Document $blueprints): array
    {
        $index = [];

        if (!is_array($blueprints->data)) {
            throw new RuntimeException('Blueprints document must contain an array of resources.');
        }

        foreach ($blueprints->data as $blueprint) {
            $blueprintIdentifier = $blueprint->attributes['identifier'];

            foreach ($blueprint->relationships['fields']->included as $field) {
                $fieldIdentifier = $field->attributes['identifier'];
                $fieldType = $field->relationships['fieldType']->data->id;

                // skip relationships
                if (in_array($fieldType, ['construct', 'constructs'], strict: true)) {
                    continue;
                }

                $column = Str::camel($fieldIdentifier);
                $method = $this->typeMapper->toColumnMethod($fieldType);

                $index[$blueprintIdentifier][$column] = $method;
            }
        }

        return $index;
    }

    /**
     * @param list<string> $fields
     * @param list<string> $columns
     *
     * @return list<string>
     */
    private function resolveMissingFields(array $fields, array $columns): array
    {
        $missing = [];

        foreach ($fields as $field) {
            if (in_array($field, $columns, strict: true)) {
                continue;
            }

            $missing[] = $field;
        }

        return $missing;
    }
}
