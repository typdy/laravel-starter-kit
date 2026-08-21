<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Typdy\StarterKit\Attributes\Blueprint;
use Typdy\StarterKit\Laravel\Models\Concerns\UsesTypdy;
use Typdy\StarterKit\Models\Contracts\Construct;

use function array_filter;
use function array_first;
use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;

use const ARRAY_FILTER_USE_BOTH;
use const JSON_THROW_ON_ERROR;

/**
 * @api
 *
 * @property string $name
 * @property string $url
 *
 * @property ?object $conversions
 * @property array<int, string> $conversionsInProgress
 *
 * @property ?string $constraintUrl
 *
 * @mago-ignore analysis:all
 */
#[Blueprint('media')]
class Media extends Model implements Construct
{
    use UsesTypdy {
        casts as parentCasts;
    }

    protected $fillable = [
        'name',
        'url',
        'conversions',
        'conversionsInProgress',
    ];

    public function constraintUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($this->conversions === null) {
                    return null;
                }

                return array_first(
                    array_filter(
                        (array) $this->conversions,
                        static fn (string $url, string $name) => str_starts_with($name, 'constraint'),
                        ARRAY_FILTER_USE_BOTH,
                    ),
                );
            },
        );
    }

    public function conversions(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (is_string($value)) {
                    $value = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
                }

                if (is_array($value)) {
                    return (object) $this->camelFields($value);
                }

                return $value;
            },
        );
    }

    protected function casts(): array
    {
        return [
            ...$this->parentCasts(),
            'conversions' => 'array',
            'conversionsInProgress' => 'array',
        ];
    }
}
