<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

use Typdy\StarterKit\Parsers\Data\Resource;

final class ContentData
{
    /**
     * @param array<string, array<string, Resource>> $resourcesByType
     * @param list<string> $seenResources
     * @param list<string> $seenRelationships
     * @param list<RelationshipEdgeData> $relationships
     */
    public function __construct(
        public array $resourcesByType = [],
        public array $seenResources = [],
        public array $seenRelationships = [],
        public array $relationships = [],
    ) {}
}
