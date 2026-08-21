<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

final readonly class FetchPlanData
{
    /**
     * @param list<FetchTaskData> $initial
     * @param list<FetchTaskData> $deferred
     */
    public function __construct(
        public FetchTaskData $global,
        public array $initial,
        public array $deferred,
    ) {}
}
