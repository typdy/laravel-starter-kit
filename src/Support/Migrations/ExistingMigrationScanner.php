<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Migrations;

use function array_values;
use function database_path;
use function dirname;
use function file_get_contents;
use function glob;
use function is_dir;
use function preg_match;
use function preg_match_all;

final readonly class ExistingMigrationScanner
{
    private const string UP_METHOD_REGEX = '/function up\(\)(?:: void)?\s*\{(.*?)\}/s';

    private const string TABLE_NAME_REGEX = '/Schema::(?:create|table)\(\'([^\']+)\'/';

    private const string COLUMN_NAME_REGEX = '/->\w+\(\'([^\']+)\'/';

    /**
     * @return array<string, list<string>>
     */
    public function scan(): array
    {
        $migrations = [];

        $files = [
            ...(glob(database_path('migrations/*.php')) ?: []),
            ...(glob(dirname(__DIR__, levels: 3) . '/migrations/*.php') ?: []),
        ];

        foreach ($files as $file) {
            if (is_dir($file)) {
                continue;
            }

            $migration = file_get_contents($file) ?: '';

            $table = $this->extractTableName($migration);

            if ($table === null) {
                continue;
            }

            $migrations[$table] = [
                ...($migrations[$table] ?? []),
                ...$this->extractTableColumns($migration),
            ];
        }

        return $migrations;
    }

    /**
     * @return list<string>
     */
    private function extractTableColumns(string $migration): array
    {
        $upMatches = [];
        $columnMatches = [];

        if (preg_match(self::UP_METHOD_REGEX, $migration, $upMatches) === false) {
            return [];
        }

        // @mago-expect analysis:possibly-invalid-argument
        if (preg_match_all(self::COLUMN_NAME_REGEX, $upMatches[1] ?? [], $columnMatches) === false) {
            return [];
        }

        return array_values($columnMatches[1] ?? []);
    }

    private function extractTableName(string $migration): ?string
    {
        $matches = [];

        if (preg_match(self::TABLE_NAME_REGEX, $migration, $matches) === 1) {
            return $matches[1] ?? null;
        }

        return null;
    }
}
