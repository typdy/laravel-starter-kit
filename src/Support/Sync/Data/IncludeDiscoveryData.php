<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

final readonly class IncludeDiscoveryData
{
    /**
     * @param array<string, list<string>> $blueprintPaths Include paths by blueprint
     * @param array<string, list<string>> $deferredBlueprintPaths Deferred include paths by blueprint
     */
    public function __construct(
        public array $blueprintPaths,
        public array $deferredBlueprintPaths,
    ) {}
}
