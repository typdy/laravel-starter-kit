<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Typdy\StarterKit\Models\Contracts\Aliasable;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Utils\Arr;

use function array_values;
use function is_array;
use function json_encode;
use function method_exists;
use function property_exists;

/**
 * @api
 *
 * @mixin Model
 */
trait SerialisesConstruct
{
    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    abstract public function camelFields(array $fields): array;

    abstract public function getBlueprint(): string;

    abstract public function isGlobal(): bool;

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    abstract public function uncamelFields(array $fields): array;

    final public function getSyncBody(): ?string
    {
        $relationships = [];

        $related = $this->getRelatedIds();

        foreach ($related as $relationKey => $relatedIds) {
            $data = null;

            if (is_array($relatedIds)) {
                $data = array_map(
                    fn (int $id) => $this->relationToRelationship($relationKey, $id),
                    $relatedIds,
                );
            } elseif ($relatedIds !== null) {
                $data = $this->relationToRelationship($relationKey, $relatedIds);
            }

            $relationships[$relationKey] = [
                'data' => $data,
                'meta' => $this->maybeGetRelationshipMeta($relationKey),
            ];
        }

        $attributes = $this->getAttributes();

        $type = $this->getBlueprint();
        $id = null;
        $meta = null;

        if (Typdy::config()->legacyTypes) {
            $type = $this->isGlobal() ? 'globals' : 'constructs';
        }

        if (property_exists($this, 'id')) {
            $id = (string) $this->id;
        }

        if (property_exists($this, 'meta') && is_array($this->meta)) {
            $meta = (object) $this->meta;
        }

        $body = [
            'data' => [
                'type' => $type,
                'id' => $id,
                'attributes' => (object) ($attributes |> $this->uncamelFields(...) |> $this->maybeAliasFields(...)),
                'relationships' => (object) (
                    $relationships |> $this->uncamelFields(...) |> $this->maybeAliasFields(...)
                ),
                'meta' => $meta,
            ],
        ];

        return json_encode($body, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function getRelationshipMeta(string $name): array;

    /**
     * @return array<string, string>
     */
    abstract protected function getRelationships(): array;

    abstract protected function toKebab(string $fieldName): string;

    /**
     * @return array<string, array<int, int>|int|null>
     */
    final protected function getRelatedIds(): array
    {
        $relations = $this->maybeGetRelationships();

        $relations |> array_values(...) |> $this->loadMissing(...);

        $relatedIds = [];

        foreach ($relations as $relationKey => $relationMethod) {
            if (!method_exists($this, $relationMethod)) {
                continue;
            }

            /** @var Collection|Model|null $related */
            $related = $this->getRelation($relationMethod);

            if ($related instanceof Collection) {
                $relatedIds[$relationKey] = $related->modelKeys();
            } elseif ($related instanceof Model) {
                $relatedIds[$relationKey] = [$related->getKey()];
            } else {
                $relatedIds[$relationKey] = null;
            }
        }

        // @mago-expect analysis:less-specific-nested-return-statement
        return $relatedIds;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    final protected function maybeAliasFields(array $fields): array
    {
        if ($this instanceof Aliasable) {
            return $this->aliasFields($fields);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    final protected function maybeGetRelationshipMeta(string $relationKey): array
    {
        return $this->getRelationshipMeta($relationKey);
    }

    /**
     * @return array<string, string>
     */
    final protected function maybeGetRelationships(): array
    {
        return $this->getRelationships();
    }

    /**
     * @return array<string, string>|null
     */
    final protected function relationToRelationship(string $relationKey, int $id): ?array
    {
        $type = null;

        /**
         * @var ?Relation $relation
         *
         * @mago-expect analysis:non-existent-property
         */
        $relation = $this->resource?->relationships[$this->toKebab($relationKey)] ?? null;

        /** @var list<Resource> **/
        $related = Arr::wrap($relation->data ?? []);

        foreach ($related as $resource) {
            if ($resource->id !== (string) $id) {
                continue;
            }

            $type = $resource->type;
            break;
        }

        if ($type === null) {
            throw new RuntimeException('Related constructs must be synced before they can be linked.');
        }

        return [
            'id' => (string) $id,
            'type' => $type,
        ];
    }
}
