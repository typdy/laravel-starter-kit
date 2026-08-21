<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Models\Concerns;

use ReflectionClass;
use ReflectionMethod;
use Typdy\StarterKit\Models\Attributes\Relationship;
use Typdy\StarterKit\Utils\Str;

use function array_key_exists;
use function array_keys;
use function property_exists;

/**
 * @api
 */
trait HasRelationships
{
    /**
     * @internal
     *
     * @var array<string, array<string, mixed>>
     */
    private array $_relationshipMetaOverrides = [];

    /**
     * @internal
     *
     * @var array<string, string>|null
     */
    private ?array $_relationships = null;

    abstract public function getProject(): string;

    abstract public function getTeam(): string;

    /**
     * @return array<string, mixed>
     */
    final public function getRelationshipMeta(string $name): array
    {
        // @mago-expect analysis:less-specific-return-statement
        return [
            // @mago-expect analysis:non-existent-property
            // @mago-expect analysis:invalid-array-element
            ...($this->resource?->relationships[$name]->meta ?? []),
            ...($this->_relationshipMetaOverrides[$name] ?? []),
        ];
    }

    /**
     * @return array<string, string>
     */
    final public function getRelationships(): array
    {
        if ($this->_relationships === null) {
            $relationships = [];

            $reflection = new ReflectionClass($this);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Relationship::class);

                if ($attributes === []) {
                    continue;
                }

                $attribute = $attributes[0]->newInstance();

                $relationships[$attribute->alias ?? $method->getName()] = $method->getName();
            }

            $this->_relationships = $relationships;
        }

        $relationships = $this->_relationships;

        /** @var array<string, mixed> $resourceRelationships */
        // @mago-expect analysis:non-existent-property
        $resourceRelationships = $this->resource->relationships ?? [];

        foreach (array_keys($resourceRelationships) as $name) {
            $property = Str::camel($name);

            if (!array_key_exists($name, $relationships) && property_exists($this, $property)) {
                $relationships[$name] = $property;
            }
        }

        return $relationships;
    }

    /**
     * @param array<string, mixed> $meta
     */
    final public function setRelationshipMeta(string $name, array $meta): void
    {
        $this->_relationshipMetaOverrides[$name] = $meta;
    }

    final protected function clearRelationshipMeta(): void
    {
        $this->_relationshipMetaOverrides = [];
    }
}
