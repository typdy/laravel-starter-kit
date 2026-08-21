<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Models\Contracts\TypdyModel;
use Typdy\StarterKit\Models\Attributes\Relationship;

#[Blueprint('beta')]
final class BetaModel extends Model implements TypdyModel
{
    use UsesTypdy;

    #[Relationship(alias: 'alpha')]
    public function alphaRelation(): BelongsTo
    {
        return $this->belongsTo(AlphaModel::class, 'alpha_id');
    }
}
