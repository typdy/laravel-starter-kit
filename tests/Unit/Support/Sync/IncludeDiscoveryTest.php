<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Typdy\StarterKit\Attributes\Blueprint as TypdyBlueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Models\Contracts\TypdyModel;
use Typdy\StarterKit\Laravel\Support\Sync\IncludeDiscovery;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Models\Attributes\Relationship;

uses(TestCase::class);

#[TypdyBlueprint('article')]
class IncludeDiscoveryArticleModel extends Model implements TypdyModel
{
    use UsesTypdy;

    #[Relationship(alias: 'category')]
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(IncludeDiscoveryCategoryModel::class, 'category_id');
    }
}

#[TypdyBlueprint('category')]
class IncludeDiscoveryCategoryModel extends Model implements TypdyModel
{
    use UsesTypdy;

    #[Relationship(alias: 'article')]
    public function articleRelation(): BelongsTo
    {
        return $this->belongsTo(IncludeDiscoveryArticleModel::class, 'article_id');
    }

    #[Relationship(alias: 'subTopic')]
    public function subTopicRelation(): BelongsTo
    {
        return $this->belongsTo(IncludeDiscoveryTopicModel::class, 'topic_id');
    }
}

#[TypdyBlueprint('topic')]
class IncludeDiscoveryTopicModel extends Model implements TypdyModel
{
    use UsesTypdy;

    #[Relationship(alias: 'editor')]
    public function editorRelation(): BelongsTo
    {
        return $this->belongsTo(IncludeDiscoveryEditorModel::class, 'editor_id');
    }
}

#[TypdyBlueprint('editor')]
class IncludeDiscoveryEditorModel extends Model implements TypdyModel
{
    use UsesTypdy;
}

class IncludeDiscoveryUserModel extends Model {}

#[TypdyBlueprint('article')]
class IncludeDiscoveryArticleWithAuthorModel extends Model implements TypdyModel
{
    use UsesTypdy;

    #[Relationship(alias: 'author')]
    public function authorRelation(): BelongsTo
    {
        return $this->belongsTo(IncludeDiscoveryUserModel::class, 'author_id');
    }
}

it('discovers include paths within depth and defers deeper paths', function () {
    $discovery = new IncludeDiscovery(maxDepth: 2);

    $result = $discovery->discover([
        new IncludeDiscoveryArticleModel(),
    ]);

    expect($result->blueprintPaths['article'])->toContain('category');
    expect($result->blueprintPaths['article'])->toContain('category.sub-topic');

    expect($result->deferredBlueprintPaths['article'])->toBe([
        'category.sub-topic.editor',
    ]);
});

it('prevents recursive traversal when a related blueprint already exists in ancestry', function () {
    $discovery = new IncludeDiscovery(maxDepth: 3);

    $result = $discovery->discover([
        new IncludeDiscoveryArticleModel(),
    ]);

    expect($result->blueprintPaths['article'])->toContain('category.article');
    expect($result->blueprintPaths['article'])->not->toContain('category.article.category');
});

it('ignores relationships whose related model is not a construct', function () {
    $discovery = new IncludeDiscovery(maxDepth: 3);

    $result = $discovery->discover([
        new IncludeDiscoveryArticleWithAuthorModel(),
    ]);

    expect($result->blueprintPaths['article'])->toBeEmpty();
    expect($result->deferredBlueprintPaths['article'])->toBeEmpty();
});
