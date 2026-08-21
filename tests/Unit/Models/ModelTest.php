<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Typdy\StarterKit\Attributes\Blueprint as TypdyBlueprint;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Models\Fixtures\TypdyModel;
use Typdy\StarterKit\Models\Attributes\Relationship;
use Typdy\StarterKit\Parsers\Data\Relation;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

uses(TestCase::class);

beforeEach(function () {
    Typdy::$config = new TypdyConfig(
        team: 'team-test',
        project: 'project-test',
    );

    Schema::dropIfExists('typdy_models');

    Schema::create('typdy_models', function (Blueprint $table) {
        $table->id();
        $table->string('identifier')->nullable();
        $table->string('team')->nullable();
        $table->string('project')->nullable();
        $table->string('title')->nullable();
        $table->json('translations')->nullable();
        $table->json('resource')->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('typdy_models');
});

it('hydrates from resource and maps common construct fields', function () {
    $resource = new Resource(
        type: 'article',
        id: '42',
        attributes: [
            'identifier' => 'article-42',
            'title' => 'Hydrated title',
            'created' => '2026-08-01T10:00:00+00:00',
            'updated' => '2026-08-02T10:00:00+00:00',
        ],
        meta: ['global' => true],
    );

    $model = new TypdyModel();
    $model->hydrateFromResource($resource);

    expect($model->id)->toBe(42);
    expect($model->resource)->toBe($resource);
    expect($model->identifier)->toBe('article-42');
    expect($model->title)->toBe('Hydrated title');
    expect($model->meta)->toBe(['global' => true]);
    expect($model->created)->toBeInstanceOf(Carbon::class);
    expect($model->updated)->toBeInstanceOf(Carbon::class);
    expect($model->getAttribute('resource'))->toBe($resource->toArray());
});

it('throws when hydrating with mismatched resource type', function () {
    $resource = new Resource(
        type: 'wrong-type',
        id: '1',
        attributes: [],
    );

    new TypdyModel()->hydrateFromResource($resource);
})->throws(InvalidArgumentException::class, "Cannot hydrate a 'article' with a resource of type 'wrong-type'.");

it('returns default sync headers and parameters', function () {
    $model = new TypdyModel();

    expect($model->getSyncHeaders())->toBeEmpty();
    expect($model->getSyncParameters())->toBeEmpty();
});

it('reads global status from meta', function () {
    $model = new TypdyModel();

    $model->meta = ['global' => true];
    expect($model->isGlobal())->toBeTrue();

    $model->meta = [];
    expect($model->isGlobal())->toBeFalse();
});

it('reflects eloquent exists state in isNew', function () {
    $model = new TypdyModel();

    expect($model->isNew())->toBeFalse();

    $model->exists = true;

    expect($model->isNew())->toBeTrue();
});

it('hydrates resource on retrieval while keeping persisted attribute values authoritative', function () {
    $resource = new Resource(
        type: 'article',
        id: '55',
        attributes: [
            'identifier' => 'article-55',
            'title' => 'From resource payload',
            'created' => '2026-08-03T10:00:00+00:00',
            'updated' => '2026-08-04T10:00:00+00:00',
        ],
        meta: ['global' => false],
    );

    DB::table('typdy_models')->insert([
        'id' => 55,
        'identifier' => null,
        'team' => 'x',
        'project' => 'y',
        'title' => 'Persisted title',
        'resource' => json_encode($resource->toArray(), JSON_THROW_ON_ERROR),
        'created' => now(),
        'updated' => now(),
    ]);

    $model = TypdyModel::query()->findOrFail(55);

    expect($model->resource)->not->toBeNull();
    expect($model->resource?->id)->toBe('55');
    expect($model->title)->toBe('Persisted title');
    expect($model->id)->toBe(55);
});

it('returns false for divergence when resource is missing', function () {
    $model = new TypdyModel();

    expect($model->hasDivergedFromTypdy())->toBeFalse();
});

it('returns false when persisted values match the hydrated resource', function () {
    $resource = new Resource(
        type: 'article',
        id: '88',
        attributes: [
            'identifier' => 'article-88',
            'title' => 'In sync title',
        ],
    );

    DB::table('typdy_models')->insert([
        'id' => 88,
        'identifier' => 'article-88',
        'team' => 'x',
        'project' => 'y',
        'title' => 'In sync title',
        'resource' => json_encode($resource->toArray(), JSON_THROW_ON_ERROR),
        'created' => now(),
        'updated' => now(),
    ]);

    $model = TypdyModel::query()->findOrFail(88);

    expect($model->hasDivergedFromTypdy())->toBeFalse();
});

it('returns true when persisted values diverge from the hydrated resource', function () {
    $resource = new Resource(
        type: 'article',
        id: '89',
        attributes: [
            'identifier' => 'article-89',
            'title' => 'API title',
        ],
    );

    DB::table('typdy_models')->insert([
        'id' => 89,
        'identifier' => 'article-89',
        'team' => 'x',
        'project' => 'y',
        'title' => 'Locally changed title',
        'resource' => json_encode($resource->toArray(), JSON_THROW_ON_ERROR),
        'created' => now(),
        'updated' => now(),
    ]);

    $model = TypdyModel::query()->findOrFail(89);

    expect($model->hasDivergedFromTypdy())->toBeTrue();
});

it('discovers relationships from attributes and resource relationship properties', function () {
    $model = new
        #[TypdyBlueprint('article')]
        class extends TypdyModel {
            #[Relationship(alias: 'hero')]
            public function heroRelation() {}

            public ?TypdyModel $secondaryStory = null;
        };

    $resource = new Resource(
        type: 'article',
        id: '100',
        relationships: [
            'secondary-story' => new Relation(name: 'secondary-story'),
        ],
    );

    $model->hydrateFromResource($resource);

    expect($model->getRelationships())->toBe([
        'hero' => 'heroRelation',
        'secondary-story' => 'secondaryStory',
    ]);
});

it('merges relationship meta from resource and local overrides', function () {
    $relation = new Relation(
        name: 'related-items',
        meta: [
            'foo' => true,
            'count' => 1,
        ],
    );

    $resource = new Resource(
        type: 'article',
        id: '101',
        relationships: [
            'related-items' => $relation,
        ],
    );

    $model = new TypdyModel();
    $model->hydrateFromResource($resource);

    $model->setRelationshipMeta('related-items', ['count' => 2, 'bar' => false]);

    expect($model->getRelationshipMeta('related-items'))->toBe([
        'foo' => true,
        'count' => 2,
        'bar' => false,
    ]);
});

it('serializes construct attributes, relationships and metadata for sync body', function () {
    Schema::dropIfExists('typdy_models_models');

    Schema::create('typdy_models_models', function (Blueprint $table) {
        $table->foreignId('model_id')->constrained('typdy_models');
        $table->foreignId('related_model_id')->constrained('typdy_models');
    });

    Typdy::$config = new TypdyConfig(
        team: 'team-test',
        project: 'project-test',
        legacyTypes: false,
    );

    $relatedResource = new Resource(
        type: 'article',
        id: '1',
        attributes: ['title' => 'Related Article'],
    );

    $relatedModel = new TypdyModel();
    $relatedModel->hydrateFromResource($relatedResource);
    $relatedModel->save();

    $relation = new Relation(
        name: 'related-articles',
        data: [
            new Resource(type: 'article', id: '1'),
        ],
    );

    $relation->associateIncluded([$relatedResource]);

    $resource = new Resource(
        type: 'article',
        id: '2',
        attributes: ['title' => 'Original'],
        relationships: [
            'related-articles' => $relation,
        ],
    );

    $model = new
        #[TypdyBlueprint('article')]
        class extends TypdyModel {
            protected $table = 'typdy_models';

            #[Relationship]
            public function relatedArticles(): BelongsToMany
            {
                return $this->BelongsToMany(TypdyModel::class, 'typdy_models_models', 'model_id', 'related_model_id');
            }

            public ?TypdyModel $secondaryStory = null;
        };

    $model->hydrateFromResource($resource);

    $model->setAttribute('title', 'Changed');
    $model->save();

    $model->relatedArticles()->sync($relatedModel);

    $model->meta = ['global' => false, 'flag' => 'x'];

    $payload = json_decode((string) $model->getSyncBody(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($payload['data']['type'])->toBe('article');
    expect($payload['data']['id'])->toBe('2');
    expect($payload['data']['attributes']['title'])->toBe('Changed');
    expect($payload['data']['relationships']['related-articles']['data'][0]['id'])->toBe('1');
    expect($payload['data']['relationships']['related-articles']['data'][0]['type'])->toBe('article');
    expect($payload['data']['meta'])->toBe(['global' => false, 'flag' => 'x']);

    Schema::dropIfExists('typdy_models_models');
});

it('throws when related models are present without known relationship types in resource', function () {
    $model = new
        #[TypdyBlueprint('article')]
        class extends TypdyModel {
            #[Relationship]
            public function author() {}
        };

    $resource = new Resource(type: 'article', id: '200');
    $model->hydrateFromResource($resource);

    $related = new TypdyModel();
    $related->setAttribute('id', 501);
    $related->exists = true;

    $model->setRelation('author', $related);

    $model->getSyncBody();
})->throws(RuntimeException::class, 'Related constructs must be synced before they can be linked.');
