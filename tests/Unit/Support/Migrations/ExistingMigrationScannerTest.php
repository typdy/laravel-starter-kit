<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Support\Migrations\ExistingMigrationScanner;
use Typdy\StarterKit\Laravel\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->createdPaths = [];

    $this->path = database_path('migrations');

    if (!is_dir($this->path)) {
        mkdir($this->path, recursive: true);
    }

    $this->createMigration = function (string $path, string $contents) {
        file_put_contents($path, $contents);
        $this->createdPaths[] = $path;
    };
});

afterEach(function () {
    foreach ($this->createdPaths as $path) {
        if (is_dir($path)) {
            rmdir($path);

            continue;
        }

        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('scans multiple migration and merges columns', function () {
    $createPath = $this->path . '/2026_01_01_000001_create_test_table.php';
    $updatePath = $this->path . '/2026_01_01_000001_add_intro_to_test_table.php';

    ($this->createMigration)($createPath, <<<'PHP'
    <?php

    return new class extends Migration {
        public function up()
        {
            Schema::create('test_table', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('body');
            });
        }
    };
    PHP);

    ($this->createMigration)($updatePath, <<<'PHP'
    <?php

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('test_table', function (Blueprint $table) {
                $table->text('intro');
            });
        }
    };
    PHP);

    $scanner = new ExistingMigrationScanner();
    $scanned = $scanner->scan();

    expect($scanned)->toHaveKey('test_table');
    expect($scanned['test_table'])->toContain('title');
    expect($scanned['test_table'])->toContain('body');
    expect($scanned['test_table'])->toContain('intro');
});

it('ignores migrations that do not contain schema create or table statements', function () {
    $path = $this->path . '/2026_01_01_000003_no_table.php';

    ($this->createMigration)($path, <<<'PHP'
    <?php

    return new class extends Migration {
        public function up()
        {
            DB::statement('SELECT 1');
        }
    };
    PHP);

    $scanner = new ExistingMigrationScanner();
    $scanned = $scanner->scan();

    expect($scanned)->not->toHaveKey('no_table');
});
