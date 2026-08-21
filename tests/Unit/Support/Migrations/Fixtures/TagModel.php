<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Support\Migrations\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Laravel\Models\Contracts\TypdyModel;

#[Blueprint('tag')]
final class TagModel extends Model implements TypdyModel
{
    use UsesTypdy;

    protected $table = 'typdy_tags';
}
