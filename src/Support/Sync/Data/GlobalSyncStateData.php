<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

final class GlobalSyncStateData
{
    /**
     * @param list<string> $blueprints Blueprints that we have found globals for
     * @param list<string> $failed Globals that we have failed to sync
     */
    public function __construct(
        public array $blueprints = [],
        public array $failed = [],
        public bool $completed = false,
    ) {}

    /**
     * @param array{
     *     blueprints: list<string>,
     *     failed: list<string>,
     *     completed: bool,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            blueprints: $data['blueprints'],
            failed: $data['failed'],
            completed: $data['completed'],
        );
    }

    /**
     * @return array{
     *     blueprints: list<string>,
     *     failed: list<string>,
     *     completed: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'blueprints' => $this->blueprints,
            'failed' => $this->failed,
            'completed' => $this->completed,
        ];
    }
}
