<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

final readonly class RelationshipEdgeData
{
    public function __construct(
        public string $sourceKey,
        public string $relationship,
        public string $targetKey,
    ) {}

    /**
     * @return array{
     *     sourceKey: string,
     *     relationship: string,
     *     targetKey: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'sourceKey' => $this->sourceKey,
            'relationship' => $this->relationship,
            'targetKey' => $this->targetKey,
        ];
    }
}
