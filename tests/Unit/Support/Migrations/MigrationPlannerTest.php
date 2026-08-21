<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Typdy\StarterKit\Attributes\Blueprint as TypdyBlueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Support\Migrations\MigrationPlanner;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;

uses(TestCase::class);

it('throws when blueprint document data is not a resource list', function () {
    $planner = new MigrationPlanner();

    $document = new Document(
        response: new Response(200),
        data: new Resource(type: 'blueprints', id: 'single-blueprint'),
    );

    $planner->plan([], [], $document);
})->throws(RuntimeException::class, 'Blueprints document must contain an array of resources.');

it('plans create migrations with mapped field types and json fallback fields', function () {
    $planner = new MigrationPlanner();

    $model = new
        #[TypdyBlueprint('article')]
        class extends Model implements Construct {
            use UsesTypdy;

            protected $table = 'typdy_articles';

            protected $fillable = ['title', 'title', 'publishedAt', 'author', 'extraPayload'];
        };

    $fields = [
        new Resource(
            type: 'fields',
            id: '1',
            attributes: ['identifier' => 'title'],
            relationships: ['fieldType' => new Relation('fieldType', new Resource(type: 'field-types', id: 'text'))],
        ),
        new Resource(
            type: 'fields',
            id: '2',
            attributes: ['identifier' => 'published-at'],
            relationships: ['fieldType' => new Relation(
                'fieldType',
                new Resource(type: 'field-types', id: 'date-time'),
            )],
        ),
        new Resource(
            type: 'fields',
            id: '3',
            attributes: ['identifier' => 'author'],
            relationships: ['fieldType' => new Relation(
                'fieldType',
                new Resource(type: 'field-types', id: 'construct'),
            )],
        ),
    ];

    $fieldRelation = new Relation(name: 'fields', data: $fields);
    $fieldRelation->associateIncluded($fields);

    $blueprints = new Document(
        response: new Response(200),
        data: [
            new Resource(
                type: 'blueprints',
                id: '123',
                attributes: ['identifier' => 'article'],
                relationships: ['fields' => $fieldRelation],
            ),
        ],
    );

    $plans = $planner->plan([$model], [], $blueprints);

    expect($plans)->toHaveCount(1);

    $plan = $plans[0];

    expect($plan->table)->toBe('typdy_articles');
    expect($plan->create)->toBeTrue();
    expect($plan->lines)->toBe([
        '$table->string(\'title\');',
        '$table->timestamp(\'publishedAt\');',
        '$table->json(\'author\')->nullable();',
        '$table->json(\'extraPayload\')->nullable();',
    ]);
});

it('plans only missing fields when table already exists', function () {
    $planner = new MigrationPlanner();

    $model = new
        #[TypdyBlueprint('article')]
        class extends Model implements Construct {
            use UsesTypdy;

            protected $table = 'typdy_articles';

            protected $fillable = ['title', 'summary'];
        };

    $fields = [
        new Resource(
            type: 'fields',
            id: '1',
            attributes: ['identifier' => 'title'],
            relationships: ['fieldType' => new Relation('fieldType', new Resource(type: 'field-types', id: 'text'))],
        ),
        new Resource(
            type: 'fields',
            id: '2',
            attributes: ['identifier' => 'summary'],
            relationships: ['fieldType' => new Relation(
                'fieldType',
                new Resource(type: 'field-types', id: 'textarea'),
            )],
        ),
    ];

    $fieldRelation = new Relation(name: 'fields', data: $fields);
    $fieldRelation->associateIncluded($fields);

    $blueprints = new Document(
        response: new Response(200),
        data: [
            new Resource(
                type: 'blueprints',
                id: '123',
                attributes: ['identifier' => 'article'],
                relationships: ['fields' => $fieldRelation],
            ),
        ],
    );

    $existing = ['typdy_articles' => ['id', 'title', 'team', 'project']];

    $plans = $planner->plan([$model], $existing, $blueprints);

    expect($plans)->toHaveCount(1);
    expect($plans[0]->create)->toBeFalse();
    expect($plans[0]->lines)->toBe([
        '$table->text(\'summary\');',
    ]);
});

it('skips existing tables when there are no missing fields', function () {
    $planner = new MigrationPlanner();

    $model = new
        #[TypdyBlueprint('article')]
        class extends Model implements Construct {
            use UsesTypdy;

            protected $table = 'typdy_articles';

            protected $fillable = ['title'];
        };

    $fields = [
        new Resource(
            type: 'fields',
            id: '1',
            attributes: ['identifier' => 'title'],
            relationships: ['fieldType' => new Relation('fieldType', new Resource(type: 'field-types', id: 'text'))],
        ),
    ];

    $fieldRelation = new Relation(name: 'fields', data: $fields);
    $fieldRelation->associateIncluded($fields);

    $blueprints = new Document(
        response: new Response(200),
        data: [
            new Resource(
                type: 'blueprints',
                id: '123',
                attributes: ['identifier' => 'article'],
                relationships: ['fields' => $fieldRelation],
            ),
        ],
    );

    $existing = ['typdy_articles' => ['id', 'title', 'team', 'project']];

    expect($planner->plan([$model], $existing, $blueprints))->toBeEmpty();
});
