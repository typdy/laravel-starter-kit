<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

use function in_array;

final class ConstructSyncStateData
{
    /**
     * @param array<string, int> $blueprintPages The total number of pages for each blueprint
     * @param array<string, list<int>> $fetchedPages Page numbers fetched for each blueprint
     * @param array<string, int> $constructsCount The total number of constructs for each blueprint
     * @param array<string, int> $blueprintFailures The number of failed fetches for each blueprint
     * @param array<string, list<int>> $failureStatuses The distinct non-200 HTTP statuses seen for each blueprint
     */
    public function __construct(
        public array $blueprintPages = [],
        public array $fetchedPages = [],
        public array $constructsCount = [],
        public array $blueprintFailures = [],
        public array $failureStatuses = [],
    ) {}

    /**
     * @param array{
     *     blueprintPages: array<string, int>,
     *     fetchedPages: array<string, list<int>>,
     *     constructsCount: array<string, int>,
     *     blueprintFailures: array<string, int>,
     *     failureStatuses?: array<string, list<int>>,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            blueprintPages: $data['blueprintPages'],
            fetchedPages: $data['fetchedPages'],
            constructsCount: $data['constructsCount'],
            blueprintFailures: $data['blueprintFailures'],
            failureStatuses: $data['failureStatuses'] ?? [],
        );
    }

    public function recordFailureStatus(string $key, int $status): void
    {
        $this->failureStatuses[$key] ??= [];

        if (!in_array($status, $this->failureStatuses[$key], strict: true)) {
            $this->failureStatuses[$key][] = $status;
        }
    }

    /**
     * @return array{
     *     blueprintPages: array<string, int>,
     *     fetchedPages: array<string, list<int>>,
     *     constructsCount: array<string, int>,
     *     blueprintFailures: array<string, int>,
     *     failureStatuses: array<string, list<int>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'blueprintPages' => $this->blueprintPages,
            'fetchedPages' => $this->fetchedPages,
            'constructsCount' => $this->constructsCount,
            'blueprintFailures' => $this->blueprintFailures,
            'failureStatuses' => $this->failureStatuses,
        ];
    }
}
