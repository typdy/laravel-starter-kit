<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Support\Migrations\RelationshipPlanner;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Migrations\Fixtures\ArticleModel;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Migrations\Fixtures\ArticleWithAuthorModel;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Migrations\Fixtures\ArticleWithCategoryModel;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Migrations\Fixtures\ArticleWithTagsModel;

uses(TestCase::class);

it('plans belongs-to and belongs-to-many migrations for typdy construct relationships', function () {
    $planner = new RelationshipPlanner();

    $plans = $planner->plan([new ArticleModel()], []);

    expect($plans)->toHaveCount(2);

    $byTable = [];

    foreach ($plans as $plan) {
        $byTable[$plan->table] = $plan;
    }

    expect($byTable)->toHaveKeys(['typdy_articles', 'article_tag']);

    expect($byTable['typdy_articles']->create)->toBeFalse();
    expect($byTable['typdy_articles']->lines)->toBe([
        '$table->foreignId(\'category_id\')->nullable()->constrained(\'typdy_categories\')->nullOnDelete();',
    ]);

    expect($byTable['article_tag']->create)->toBeTrue();
    expect($byTable['article_tag']->lines)->toBe([
        '$table->foreignId(\'article_id\')->constrained(\'typdy_articles\')->cascadeOnDelete();',
        '$table->foreignId(\'tag_id\')->constrained(\'typdy_tags\')->cascadeOnDelete();',
        '$table->unique([\'article_id\', \'tag_id\']);',
    ]);
    expect($byTable['article_tag']->stub)->toBe('table-migration');
});

it('ignores relationships whose related model is not a typdy construct', function () {
    $planner = new RelationshipPlanner();

    expect($planner->plan([new ArticleWithAuthorModel()], []))->toBeEmpty();
});

it('skips belongs-to migrations when foreign keys already exist', function () {
    $planner = new RelationshipPlanner();

    $existing = [
        'typdy_articles' => ['id', 'category_id'],
    ];

    expect($planner->plan([new ArticleWithCategoryModel()], $existing))->toBeEmpty();
});

it('creates pivot migration once and skips when pivot table already exists', function () {
    $planner = new RelationshipPlanner();

    $plans = $planner->plan([
        new ArticleWithTagsModel(),
        new ArticleWithTagsModel(),
    ], []);

    expect($plans)->toHaveCount(1);
    expect($plans[0]->table)->toBe('article_tag');

    $existingPivot = [
        'article_tag' => ['article_id', 'tag_id'],
    ];

    expect($planner->plan([new ArticleWithTagsModel()], $existingPivot))->toBeEmpty();
});
