<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Migrations\Data;

final readonly class MigrationPlanData
{
    /**
     * @param array<int, string> $lines
     */
    public function __construct(
        public string $table,
        public bool $create,
        public array $lines,
        public ?string $name = null,
        public ?string $stub = null,
    ) {}

    public function migrationName(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        return $this->create
            ? "create_{$this->table}_table"
            : "update_{$this->table}_table";
    }

    public function schemaMethod(): string
    {
        return $this->create ? 'create' : 'table';
    }

    public function stubName(): string
    {
        return $this->stub ?? "{$this->schemaMethod()}-migration";
    }
}
