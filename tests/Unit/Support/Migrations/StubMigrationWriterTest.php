<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Support\Migrations\Data\MigrationPlanData;
use Typdy\StarterKit\Laravel\Support\Migrations\StubMigrationWriter;
use Typdy\StarterKit\Laravel\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->stubPath = sys_get_temp_dir() . '/typdy-stub-writer-tests-' . uniqid('', more_entropy: true);
    $this->generatedMigrationPaths = [];

    if (!is_dir($this->stubPath)) {
        mkdir($this->stubPath, recursive: true);
    }
});

afterEach(function () {
    foreach ($this->generatedMigrationPaths as $path) {
        if (!file_exists($path)) {
            continue;
        }

        unlink($path);
    }

    foreach (glob($this->stubPath . '/*.stub') ?: [] as $stub) {
        unlink($stub);
    }

    if (is_dir($this->stubPath)) {
        rmdir($this->stubPath);
    }
});

it('writes a create migration from a stub and replaces placeholders', function () {
    file_put_contents($this->stubPath . '/create-migration.stub', <<<'PHP'
    <?php
        Schema::{{METHOD}}('{{TABLE}}', function (Blueprint $table) {
            {{COLUMNS}}
        });
    PHP);

    $writer = new StubMigrationWriter($this->stubPath);

    $plan = new MigrationPlanData(
        table: 'typdy_articles',
        create: true,
        lines: [
            '$table->string(\'title\');',
            '$table->text(\'body\');',
        ],
    );

    $relativePath = $writer->write($plan);

    expect($relativePath)->toMatch('/^migrations\/\d{4}_\d{2}_\d{2}_\d{6}_\d+_create_typdy_articles_table\.php$/');

    $absolutePath = database_path($relativePath);
    $this->generatedMigrationPaths[] = $absolutePath;

    expect($absolutePath)->toBeFile();

    $content = (string) file_get_contents($absolutePath);

    expect($content)->toContain("Schema::create('typdy_articles'");
    expect($content)->toContain('$table->string(\'title\');');
    expect($content)->toContain('$table->text(\'body\');');
});

it('writes a table migration using a custom stub name', function () {
    file_put_contents($this->stubPath . '/custom-table.stub', <<<'PHP'
    <?php
        Schema::{{METHOD}}('{{TABLE}}', function (Blueprint $table) {
            {{COLUMNS}}
        });
    PHP);

    $writer = new StubMigrationWriter($this->stubPath);

    $plan = new MigrationPlanData(
        table: 'typdy_articles',
        create: false,
        lines: [
            '$table->boolean(\'featured\')->default(false);',
        ],
        name: 'add_featured_to_typdy_articles_table',
        stub: 'custom-table',
    );

    $relativePath = $writer->write($plan);

    expect($relativePath)->toMatch(
        '/^migrations\/\d{4}_\d{2}_\d{2}_\d{6}_\d+_add_featured_to_typdy_articles_table\.php$/',
    );

    $absolutePath = database_path($relativePath);
    $this->generatedMigrationPaths[] = $absolutePath;

    $content = (string) file_get_contents($absolutePath);

    expect($content)->toContain("Schema::table('typdy_articles'");
    expect($content)->toContain('$table->boolean(\'featured\')->default(false);');
});
