<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Typdy\StarterKit\Api\Contracts\Client as ClientContract;
use Typdy\StarterKit\Attributes\Blueprint as TypdyBlueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Models\Contracts\TypdyModel;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Models\Attributes\Relationship;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\TypdyConfig;

uses(TestCase::class);

#[TypdyBlueprint('article')]
final class SyncArticleModel extends Model implements TypdyModel
{
    use UsesTypdy;

    protected $table = 'sync_articles';

    protected $fillable = ['title'];

    #[Relationship(alias: 'category')]
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(SyncCategoryModel::class, 'category_id');
    }

    #[Relationship(alias: 'tags')]
    public function tagsRelation(): BelongsToMany
    {
        return $this->belongsToMany(SyncTagModel::class, 'sync_article_tag', 'article_id', 'tag_id');
    }
}

#[TypdyBlueprint('category')]
final class SyncCategoryModel extends Model implements TypdyModel
{
    use UsesTypdy;

    protected $table = 'sync_categories';
}

#[TypdyBlueprint('tag')]
final class SyncTagModel extends Model implements TypdyModel
{
    use UsesTypdy;

    protected $table = 'sync_tags';
}

beforeEach(function () {
    $this->privateStoragePath = base_path('workbench/storage/framework/testing/typdy-sync-relations');

    File::deleteDirectory($this->privateStoragePath);
    File::ensureDirectoryExists($this->privateStoragePath);

    Typdy::$config = new TypdyConfig(
        team: 'team-test',
        project: 'project-test',
        privateStoragePath: $this->privateStoragePath,
    );

    Schema::dropIfExists('sync_article_tag');
    Schema::dropIfExists('sync_articles');
    Schema::dropIfExists('sync_categories');
    Schema::dropIfExists('sync_tags');

    Schema::create('sync_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('identifier')->nullable();
        $table->string('team');
        $table->string('project');
        $table->string('title')->nullable();
        $table->json('translations')->nullable();
        $table->json('resource');
        $table->timestamp('created');
        $table->timestamp('updated');
    });

    Schema::create('sync_tags', function (Blueprint $table): void {
        $table->id();
        $table->string('identifier')->nullable();
        $table->string('team');
        $table->string('project');
        $table->string('title')->nullable();
        $table->json('translations')->nullable();
        $table->json('resource');
        $table->timestamp('created');
        $table->timestamp('updated');
    });

    Schema::create('sync_articles', function (Blueprint $table): void {
        $table->id();
        $table->string('identifier')->nullable();
        $table->string('team');
        $table->string('project');
        $table->string('title')->nullable();

        /** @var ForeignIdColumnDefinition $categoryColumn */
        $categoryColumn = $table->foreignId('category_id')->nullable();
        $categoryColumn->constrained('sync_categories')->nullOnDelete();

        $table->json('translations')->nullable();
        $table->json('resource');
        $table->timestamp('created');
        $table->timestamp('updated');
    });

    Schema::create('sync_article_tag', function (Blueprint $table): void {
        $table->foreignId('article_id')->constrained('sync_articles')->cascadeOnDelete();
        $table->foreignId('tag_id')->constrained('sync_tags')->cascadeOnDelete();
        $table->unsignedInteger('meta_order')->default(0);
        $table->unique(['article_id', 'tag_id']);
    });
});

afterEach(function () {
    Schema::dropIfExists('sync_article_tag');
    Schema::dropIfExists('sync_articles');
    Schema::dropIfExists('sync_categories');
    Schema::dropIfExists('sync_tags');

    File::deleteDirectory($this->privateStoragePath);
});

it('removes belongs-to and belongs-to-many links when single construct sync payload contains null and empty relationships', function () {
    DB::table('sync_categories')->insert([
        'id' => 1,
        'identifier' => 'cat-1',
        'team' => 'team-test',
        'project' => 'project-test',
        'title' => 'Category 1',
        'translations' => '{}',
        'resource' => '{"type":"category","id":"1","attributes":{}}',
        'created' => '2026-01-01 00:00:00',
        'updated' => '2026-01-01 00:00:00',
    ]);

    DB::table('sync_tags')->insert([
        [
            'id' => 10,
            'identifier' => 'tag-10',
            'team' => 'team-test',
            'project' => 'project-test',
            'title' => 'Tag 10',
            'translations' => '{}',
            'resource' => '{"type":"tag","id":"10","attributes":{}}',
            'created' => '2026-01-01 00:00:00',
            'updated' => '2026-01-01 00:00:00',
        ],
        [
            'id' => 11,
            'identifier' => 'tag-11',
            'team' => 'team-test',
            'project' => 'project-test',
            'title' => 'Tag 11',
            'translations' => '{}',
            'resource' => '{"type":"tag","id":"11","attributes":{}}',
            'created' => '2026-01-01 00:00:00',
            'updated' => '2026-01-01 00:00:00',
        ],
    ]);

    DB::table('sync_articles')->insert([
        'id' => 100,
        'identifier' => 'article-100',
        'team' => 'team-test',
        'project' => 'project-test',
        'title' => 'Before Sync',
        'category_id' => 1,
        'translations' => '{}',
        'resource' => '{"type":"article","id":"100","attributes":{}}',
        'created' => '2026-01-01 00:00:00',
        'updated' => '2026-01-01 00:00:00',
    ]);

    DB::table('sync_article_tag')->insert([
        ['article_id' => 100, 'tag_id' => 10],
        ['article_id' => 100, 'tag_id' => 11],
    ]);

    $client = mock(ClientContract::class);
    $client
        ->shouldReceive('request')
        ->once()
        ->andReturn(
            new Response(
                200,
                body: <<<'JSON'
                {
                  "data": {
                    "type": "article",
                    "id": "100",
                    "attributes": {
                      "identifier": "article-100",
                      "title": "After Sync",
                      "translations": {},
                      "created": "2026-01-02T00:00:00+00:00",
                      "updated": "2026-01-02T00:00:00+00:00"
                    },
                    "relationships": {
                      "category": {"data": null},
                      "tags": {"data": []}
                    }
                  }
                }
                JSON,
            ),
        );

    app()->instance(ClientContract::class, $client);

    $articleModel = new SyncArticleModel();

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'article')
        ->andReturn($articleModel);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$articleModel, new SyncTagModel()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'article')
        ->andReturn($repo);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--blueprint' => 'article', '--id' => 100])
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    $article = SyncArticleModel::query()->find(100);

    expect($article)->not->toBeNull();
    expect($article?->category_id)->toBeNull();
    expect(DB::table('sync_article_tag')->where('article_id', 100)->count())->toBe(0);
});

it('resolves all models for persistence when syncing a single construct so related models can be linked', function () {
    $client = mock(ClientContract::class);
    $client
        ->shouldReceive('request')
        ->once()
        ->andReturn(
            new Response(
                200,
                body: <<<'JSON'
                {
                  "data": {
                    "type": "article",
                    "id": "100",
                    "attributes": {
                      "identifier": "article-100",
                      "title": "Linked",
                      "translations": {},
                      "created": "2026-01-02T00:00:00+00:00",
                      "updated": "2026-01-02T00:00:00+00:00"
                    },
                    "relationships": {
                      "tags": {"data": [{"type": "tag", "id": "10", "meta": {"order": 0}}]}
                    }
                  },
                  "included": [
                    {"type":"tag","id":"10","attributes":{"identifier":"tag-10","title":"Tag 10","translations":{},"created":"2026-01-01T00:00:00+00:00","updated":"2026-01-01T00:00:00+00:00"}}
                  ]
                }
                JSON,
            ),
        );

    app()->instance(ClientContract::class, $client);

    $articleModel = new SyncArticleModel();

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'article')
        ->andReturn($articleModel);
    // the scoped lookup only returns the article, so the tag model must come from resolveMany
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$articleModel, new SyncTagModel()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'article')
        ->andReturn($repo);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--blueprint' => 'article', '--id' => 100])
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    expect(SyncTagModel::query()->find(10))->not->toBeNull();
    expect(DB::table('sync_article_tag')->where('article_id', 100)->pluck('tag_id')->all())->toBe([10]);
});

it('persists to-many relationship order from resource meta order onto the pivot', function () {
    $tag = static fn (int $id): string => <<<JSON
    {"type":"tag","id":"{$id}","attributes":{"identifier":"tag-{$id}","title":"Tag {$id}","translations":{},"created":"2026-01-01T00:00:00+00:00","updated":"2026-01-01T00:00:00+00:00"}}
    JSON;

    $client = mock(ClientContract::class);
    $client
        ->shouldReceive('request')
        ->andReturn(
            // globals
            new Response(200, body: '{"data": []}'),
            // article page count
            new Response(200, body: '{"data": [], "meta": {"total": 1}}'),
            // article page, tags are listed as 10, 11, 12 but ordered 12, 10, 11 by meta.order
            new Response(
                200,
                body: <<<JSON
                {
                  "data": [
                    {
                      "type": "article",
                      "id": "100",
                      "attributes": {
                        "identifier": "article-100",
                        "title": "Ordered",
                        "translations": {},
                        "created": "2026-01-02T00:00:00+00:00",
                        "updated": "2026-01-02T00:00:00+00:00"
                      },
                      "relationships": {
                        "tags": {
                          "data": [
                            {"type": "tag", "id": "10", "meta": {"order": 1}},
                            {"type": "tag", "id": "11", "meta": {"order": 2}},
                            {"type": "tag", "id": "12", "meta": {"order": 0}}
                          ]
                        }
                      }
                    }
                  ],
                  "included": [{$tag(10)}, {$tag(11)}, {$tag(12)}]
                }
                JSON,
            ),
            // everything after this (tag page count) reports nothing left to fetch
            new Response(200, body: '{"data": [], "meta": {"total": 0}}'),
        );

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveMany')
        ->twice()
        ->with('team-test', 'project-test')
        ->andReturn([new SyncArticleModel(), new SyncTagModel()]);

    $articleRepo = mock(Collection::class);
    $articleRepo->shouldReceive('isGlobal')->andReturn(false);
    $articleRepo->shouldReceive('getBlueprint')->andReturn('article');

    $tagRepo = mock(Collection::class);
    $tagRepo->shouldReceive('isGlobal')->andReturn(false);
    $tagRepo->shouldReceive('getBlueprint')->andReturn('tag');

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([$articleRepo, $tagRepo]);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync')
        ->expectsConfirmation('Proceed with syncronization of the above content?', 'yes')
        ->assertSuccessful();

    $pivot = DB::table('sync_article_tag')
        ->where('article_id', 100)
        ->orderBy('meta_order')
        ->pluck('meta_order', 'tag_id')
        ->all();

    expect($pivot)->toBe([12 => 0, 10 => 1, 11 => 2]);

    $article = SyncArticleModel::query()->find(100);

    expect($article?->tagsRelation()->get()->modelKeys())->toBe([12, 10, 11]);
});

it('orders plain belongs-to-many relations to typdy constructs by pivot meta_order', function () {
    $row = static fn (string $type, int $id): array => [
        'id' => $id,
        'identifier' => "{$type}-{$id}",
        'team' => 'team-test',
        'project' => 'project-test',
        'title' => "{$type} {$id}",
        'translations' => '{}',
        'resource' => "{\"type\":\"{$type}\",\"id\":\"{$id}\",\"attributes\":{}}",
        'created' => '2026-01-01 00:00:00',
        'updated' => '2026-01-01 00:00:00',
    ];

    DB::table('sync_tags')->insert([$row('tag', id: 10), $row('tag', id: 11), $row('tag', id: 12)]);
    DB::table('sync_articles')->insert($row('article', id: 100));

    DB::table('sync_article_tag')->insert([
        ['article_id' => 100, 'tag_id' => 10, 'meta_order' => 2],
        ['article_id' => 100, 'tag_id' => 11, 'meta_order' => 0],
        ['article_id' => 100, 'tag_id' => 12, 'meta_order' => 1],
    ]);

    $article = SyncArticleModel::query()->find(100);

    expect($article?->tagsRelation()->get()->modelKeys())->toBe([11, 12, 10]);

    $eager = SyncArticleModel::query()->with('tagsRelation')->find(100);

    expect($eager?->tagsRelation->modelKeys())->toBe([11, 12, 10]);
});

it('logs a single failure digest with status codes in non-interactive mode and still completes', function () {
    Log::spy();

    $client = mock(ClientContract::class);
    $client->shouldReceive('request')->andReturn(new Response(401, body: '{"data": null}'));

    app()->instance(ClientContract::class, $client);

    $modelResolver = mock(ResolvesModels::class);
    $modelResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'article')
        ->andReturn(new SyncArticleModel());
    $modelResolver
        ->shouldReceive('resolveMany')
        ->once()
        ->with('team-test', 'project-test')
        ->andReturn([new SyncArticleModel(), new SyncTagModel()]);

    $repo = mock(Collection::class);
    $repo->shouldReceive('isGlobal')->andReturn(false);

    $repoResolver = mock(ResolvesRepositories::class);
    $repoResolver
        ->shouldReceive('resolveOne')
        ->once()
        ->with('team-test', 'project-test', 'article')
        ->andReturn($repo);

    app()->instance(ResolvesModels::class, $modelResolver);
    app()->instance(ResolvesRepositories::class, $repoResolver);

    $this
        ->artisan('typdy:eloquent:sync', ['--blueprint' => 'article', '--id' => 100, '--no-interaction' => true])
        ->assertSuccessful();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(
            static fn (string $message, array $context): bool => (
                $message === 'typdy eloquent sync completed with failures.'
                && $context['blueprint'] === 'article'
                && $context['failures'] === ['article' => 1]
                && $context['statuses'] === ['article' => [401]]
            ),
        );
});
