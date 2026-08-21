<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

final readonly class FetchTaskData
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public string $path,
        public string $blueprint,
        public array $parameters = [],
    ) {}
}
