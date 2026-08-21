<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Support\Migrations\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Models\Contracts\TypdyModel;
use Typdy\StarterKit\Models\Attributes\Relationship;

#[Blueprint('article')]
final class ArticleWithCategoryModel extends Model implements TypdyModel
{
    use UsesTypdy;

    protected $table = 'typdy_articles';

    #[Relationship(alias: 'category')]
    public function relatedCategory(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }
}
