<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Migrations;

use Typdy\StarterKit\Laravel\Support\Migrations\Data\MigrationPlanData;

use function database_path;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function now;
use function str_replace;

final readonly class StubMigrationWriter
{
    public function __construct(
        private string $stubPath = __DIR__ . '/stubs',
    ) {}

    public function write(MigrationPlanData $plan): string
    {
        $timestamp = now()->format('Y_m_d_His_u');

        $file = "{$timestamp}_{$plan->migrationName()}.php";

        $path = database_path("migrations/{$file}");

        $stub = file_get_contents("{$this->stubPath}/{$plan->stubName()}.stub") ?: '';

        $columns = implode("\n            ", $plan->lines);

        $content = str_replace(
            ['{{METHOD}}', '{{TABLE}}', '{{COLUMNS}}'],
            [$plan->schemaMethod(), $plan->table, $columns],
            $stub,
        );

        file_put_contents($path, $content);

        return "migrations/{$file}";
    }
}
