<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Typdy\StarterKit\Concerns\HasBlueprint;
use Typdy\StarterKit\Concerns\HasProject;
use Typdy\StarterKit\Models\Concerns\HasCamelFields;
use Typdy\StarterKit\Models\Concerns\HasMeta;
use Typdy\StarterKit\Models\Concerns\HasSignature;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Resource;
use UnitEnum;

use function array_key_exists;
use function array_keys;
use function get_object_vars;
use function is_array;
use function is_object;
use function ksort;
use function property_exists;

/**
 * @api
 *
 * @mixin Model
 * @mixin Construct
 */
trait UsesTypdy
{
    use HasBlueprint;
    use HasCamelFields;
    use HasMeta;
    use HasProject;
    use HasRelationships;
    use HasSignature;
    use SerialisesConstruct;

    const CREATED_AT = 'created';

    const UPDATED_AT = 'updated';

    public protected(set) ?Resource $resource = null;

    public protected(set) ?int $id = null;

    public ?string $identifier = null;

    public ?Carbon $created = null {
        set(string|Carbon|null $value) {
            if ($value !== null) {
                $this->created = Carbon::parse($value);
            }
        }
    }

    public ?Carbon $updated = null {
        set(string|Carbon|null $value) {
            if ($value !== null) {
                $this->updated = Carbon::parse($value);
            }
        }
    }

    /**
     * @mago-expect analysis:possibly-non-existent-method
     */
    protected static function booted(): void
    {
        static::saving(static function (self $model): void {
            $model->setAttribute('team', $model->getTeam());
            $model->setAttribute('project', $model->getProject());

            if ($model->resource === null) {
                return;
            }

            // in case actual properties are set on the model, we'll sync them
            //  to attributes on save
            $attributes = array_keys($model->camelFields($model->resource->attributes));

            foreach ($attributes as $key) {
                if (!property_exists($model, $key)) {
                    continue;
                }

                // @mago-expect analysis:mixed-assignment
                $original = $model->getOriginal($key);

                // @mago-expect analysis:mixed-assignment
                $current = $model->getAttribute($key);

                // only if the attribute has its original value, otherwise we
                //  assume the attribute is more up-to-date than the property
                if ($original !== $current) {
                    continue;
                }

                // @mago-expect analysis:string-member-selector
                $model->setAttribute($key, $model->$key);
            }
        });
    }

    #[Scope]
    public function collection(Builder $query, string $collection): void
    {
        $query->where('collection', $collection);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSyncHeaders(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSyncParameters(): array
    {
        return [];
    }

    /**
     * @mago-expect analysis:mixed-assignment duh, checking for divergence with mixed values
     */
    public function hasDivergedFromTypdy(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $resourceAttributes = $this->camelFields($this->resource->attributes);

        foreach ($resourceAttributes as $key => $value) {
            if (!array_key_exists($key, $this->getAttributes())) {
                continue;
            }

            $persisted = $this->getOriginal($key);

            $normalizedPersisted = $this->normaliseForDivergenceCheck($persisted);
            $normalizedValue = $this->normaliseForDivergenceCheck($value);

            if ($normalizedPersisted !== $normalizedValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @mago-expect analysis:mixed-property-type-coercion
     */
    public function hydrateFromResource(Resource $resource): Construct
    {
        if ($resource->type !== $this->getBlueprint()) {
            throw new InvalidArgumentException(
                "Cannot hydrate a '{$this->getBlueprint()}' with a resource of type '{$resource->type}'.",
            );
        }

        $this->setKeyType('int');
        $this->setKeyName('id');

        $this->resource = $resource;

        $this->clearRelationshipMeta();

        $attributes = $this->camelFields($resource->attributes);

        $this->id = (int) $resource->id;
        $this->identifier = $attributes['identifier'] ?? null;
        $this->created = $attributes['created'] ?? null;
        $this->updated = $attributes['updated'] ?? null;

        $this->setAttribute('id', $this->id);
        $this->setAttribute('identifier', $this->identifier);
        $this->setAttribute('resource', $resource->toArray());
        $this->setAttribute('created', $this->created);
        $this->setAttribute('updated', $this->updated);

        $fillableAttributes = [];

        // @mago-expect analysis:mixed-assignment
        foreach ($attributes as $key => $value) {
            if (!$this->isFillable($key)) {
                continue;
            }

            $fillableAttributes[$key] = $value;
        }

        $this->fill($fillableAttributes);

        // @mago-expect analysis:mixed-assignment
        foreach ($fillableAttributes as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }

            // @mago-expect analysis:string-member-selector
            $this->$key = $value;
        }

        $this->meta = $resource->meta;

        // @mago-expect analysis:invalid-return-statement
        return $this;
    }

    public function isGlobal(): bool
    {
        // @mago-expect analysis:mixed-operand
        return (bool) ($this->meta['global'] ?? false);
    }

    public function isNew(): bool
    {
        // @mago-expect analysis:possibly-non-existent-property
        return $this->exists;
    }

    /**
     * @param array<string, mixed>|object $attributes
     * @param UnitEnum|string|null $connection
     */
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        /** @var array<string, mixed> $attributes */
        $attributes = is_object($attributes) ? get_object_vars($attributes) : $attributes;

        $model = $this->newInstance([], exists: true);

        $model->setConnection($connection ?? $this->getConnectionName());

        $model->setRawAttributes($attributes, sync: true);

        /**
         * @var array{
         *     type: string,
         *     id: string,
         *     attributes?: array<string, mixed>,
         *     meta?: array<string, mixed>,
         *     relationships?: array<string, array<string, mixed>>,
         * }|null $resourceAttribute
         */
        $resourceAttribute = $model->getAttribute('resource');

        if ($model->resource === null && $resourceAttribute !== null) {
            $model->hydrateFromResource(Resource::fromArray($resourceAttribute));
        }

        $model->setRawAttributes($attributes, sync: true);

        // promote any attributes to actual properties on the model, if they
        //  exist
        foreach (array_keys($attributes) as $key) {
            if ($key === 'resource') {
                continue;
            }

            if (!property_exists($model, $key)) {
                continue;
            }

            // @mago-expect analysis:string-member-selector
            $model->$key = $model->getAttribute($key);
        }

        // @mago-expect analysis:invalid-method-access mago missed the mixed i guess
        // @mago-expect analysis:non-documented-method
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    #[Scope]
    public function project(Builder $query, string $project): void
    {
        $query->where('project', $project);
    }

    #[Scope]
    public function team(Builder $query, string $team): void
    {
        $query->where('team', $team);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'translations' => 'object',
            'resource' => 'array',
            'created' => 'datetime',
            'updated' => 'datetime',
        ];
    }

    private function normaliseForDivergenceCheck(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        ksort($value);

        // @mago-expect analysis:mixed-assignment
        foreach ($value as $key => $nested) {
            $value[$key] = $this->normaliseForDivergenceCheck($nested);
        }

        return $value;
    }
}
