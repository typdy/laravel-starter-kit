<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Models\Contracts\TypdyModel;
use Typdy\StarterKit\Models\Attributes\Relationship;

#[Blueprint('article')]
final class ArticleModel extends Model implements TypdyModel
{
    use UsesTypdy;

    #[Relationship(alias: 'category')]
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }
}
