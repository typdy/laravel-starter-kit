<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Typdy\StarterKit\Api\Contracts\Client as ClientContract;
use Typdy\StarterKit\Attributes\Blueprint as TypdyBlueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

uses(TestCase::class);

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team-test',
        project: 'project-test',
        privateStoragePath: base_path('workbench/storage/framework/testing/typdy-migrations'),
    );

    $this->baselineMigrationFiles = glob(database_path('migrations/*.php')) ?: [];
});

afterEach(function () {
    $currentFiles = glob(database_path('migrations/*.php')) ?: [];

    foreach ($currentFiles as $file) {
        if (in_array($file, $this->baselineMigrationFiles, strict: true)) {
            continue;
        }

        File::delete($file);
    }

    File::deleteDirectory(base_path('workbench/storage/framework/testing/typdy-migrations'));
});

it('generates migrations from the primary interactive flow', function () {
    $model = new
        #[TypdyBlueprint('feature-article')]
        class extends Model implements Construct {
            use UsesTypdy;

            protected $table = 'typdy_feature_articles';

            protected $fillable = ['title'];
        };

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$model]);

    app()->instance(ResolvesModels::class, $modelResolver);

    $client = mock(ClientContract::class);
    $client
        ->shouldReceive('request')
        ->once()
        ->andReturn(
            new Response(
                200,
                body: <<<'JSON'
                {
                  "data": [
                    {
                      "type": "blueprints",
                      "id": "123",
                      "attributes": {"identifier": "feature-article"},
                      "relationships": {
                        "fields": {
                          "data": [
                            {"type": "fields", "id": "1234"}
                          ]
                        }
                      }
                    }
                  ],
                  "included": [
                    {
                      "type": "fields",
                      "id": "1234",
                      "attributes": {"identifier": "title"},
                      "relationships": {
                        "fieldType": {
                          "data": {"type": "field-types", "id": "text"}
                        }
                      }
                    }
                  ]
                }
                JSON,
            ),
        );

    app()->instance(ClientContract::class, $client);

    $this
        ->artisan('typdy:eloquent:migrations')
        ->expectsPromptsTable(
            headers: ['Migration Name', 'Table', 'Type'],
            rows: [['create_typdy_feature_articles_table', 'typdy_feature_articles', 'create']],
        )
        ->expectsConfirmation('Proceed with generating the above migrations?', 'yes')
        ->expectsPromptsInfo('Migrations generated successfully:')
        ->assertSuccessful();

    $generated = array_values(
        array_filter(
            glob(database_path('migrations/*.php')) ?: [],
            static fn (string $path): bool => str_contains($path, 'create_typdy_feature_articles_table.php'),
        ),
    );

    expect($generated)->toHaveCount(1);

    $content = (string) file_get_contents($generated[0]);

    expect($content)->toContain("Schema::create('typdy_feature_articles'");
    expect($content)->toContain("\$table->string('title');");
});

it('returns early when no models are resolved', function () {
    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([]);

    app()->instance(ResolvesModels::class, $modelResolver);

    $this
        ->artisan('typdy:eloquent:migrations')
        ->expectsPromptsInfo('No migrations to create.')
        ->assertSuccessful();
});

it('cancels migration generation when confirmation is declined', function () {
    $model = new
        #[TypdyBlueprint('feature-article')]
        class extends Model implements Construct {
            use UsesTypdy;

            protected $table = 'typdy_feature_articles';

            protected $fillable = ['title'];
        };

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$model]);

    app()->instance(ResolvesModels::class, $modelResolver);

    $client = mock(ClientContract::class);
    $client
        ->shouldReceive('request')
        ->once()
        ->andReturn(
            new Response(
                200,
                body: <<<'JSON'
                {
                  "data": [
                    {
                      "type": "blueprints",
                      "id": "123",
                      "attributes": {"identifier": "feature-article"},
                      "relationships": {
                        "fields": {
                          "data": [
                            {"type": "fields", "id": "1234"}
                          ]
                        }
                      }
                    }
                  ],
                  "included": [
                    {
                      "type": "fields",
                      "id": "1234",
                      "attributes": {"identifier": "title"},
                      "relationships": {
                        "fieldType": {
                          "data": {"type": "field-types", "id": "text"}
                        }
                      }
                    }
                  ]
                }
                JSON,
            ),
        );

    app()->instance(ClientContract::class, $client);

    $this
        ->artisan('typdy:eloquent:migrations')
        ->expectsConfirmation('Proceed with generating the above migrations?', 'no')
        ->expectsOutputToContain('Migration generation cancelled.')
        ->assertSuccessful();

    $generated = array_values(
        array_filter(
            glob(database_path('migrations/*.php')) ?: [],
            static fn (string $path): bool => str_contains($path, 'create_typdy_feature_articles_table.php'),
        ),
    );

    expect($generated)->toHaveCount(0);
});
