<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Models\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Models\Contracts\Construct;

#[Blueprint('article')]
class TypdyModel extends Model implements Construct
{
    use UsesTypdy;

    public $timestamps = false;

    public ?string $title = null;

    protected $fillable = [
        'title',
    ];
}
