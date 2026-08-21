<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures;

use Carbon\Carbon;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Resource;

final class FakeConstruct implements Construct
{
    public ?Resource $resource = null;

    public ?int $id = null;

    public ?string $identifier = null;

    /**
     * @var array<string, mixed>
     */
    public array $meta = [];

    public ?Carbon $created = null;

    public ?Carbon $updated = null;

    public function getBlueprint(): string
    {
        return 'fake';
    }

    public function getProject(): string
    {
        return 'project';
    }

    public function getRelationshipMeta(string $name): array
    {
        return [];
    }

    public function getSignature(): string
    {
        return 'fake-signature';
    }

    public function getSyncBody(): ?string
    {
        return null;
    }

    public function getSyncHeaders(): array
    {
        return [];
    }

    public function getSyncParameters(): array
    {
        return [];
    }

    public function getTeam(): string
    {
        return 'team';
    }

    public function hydrateFromResource(Resource $resource): Construct
    {
        $this->resource = $resource;

        return $this;
    }

    public function isGlobal(): bool
    {
        return false;
    }

    public function isNew(): bool
    {
        return true;
    }
}
