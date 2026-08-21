<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Resolvers;

use Override;
use Typdy\StarterKit\Laravel\Models\Media;
use Typdy\StarterKit\Resolvers\ModelResolver as BaseModelResolver;

/**
 * @api
 */
class ModelResolver extends BaseModelResolver
{
    #[Override]
    protected function getDefaultModels(): array
    {
        return [
            'media' => Media::class,
        ];
    }
}
