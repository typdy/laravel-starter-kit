<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

final class ConstructSyncStateData
{
    /**
     * @param array<string, int> $blueprintPages The total number of pages for each blueprint
     * @param array<string, list<int>> $fetchedPages Page numbers fetched for each blueprint
     * @param array<string, int> $constructsCount The total number of constructs for each blueprint
     * @param array<string, int> $blueprintFailures The number of failed fetches for each blueprint
     */
    public function __construct(
        public array $blueprintPages = [],
        public array $fetchedPages = [],
        public array $constructsCount = [],
        public array $blueprintFailures = [],
    ) {}

    /**
     * @param array{
     *     blueprintPages: array<string, int>,
     *     fetchedPages: array<string, list<int>>,
     *     constructsCount: array<string, int>,
     *     blueprintFailures: array<string, int>,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            blueprintPages: $data['blueprintPages'],
            fetchedPages: $data['fetchedPages'],
            constructsCount: $data['constructsCount'],
            blueprintFailures: $data['blueprintFailures'],
        );
    }

    /**
     * @return array{
     *     blueprintPages: array<string, int>,
     *     fetchedPages: array<string, list<int>>,
     *     constructsCount: array<string, int>,
     *     blueprintFailures: array<string, int>,
     * }
     */
    public function toArray(): array
    {
        return [
            'blueprintPages' => $this->blueprintPages,
            'fetchedPages' => $this->fetchedPages,
            'constructsCount' => $this->constructsCount,
            'blueprintFailures' => $this->blueprintFailures,
        ];
    }
}
