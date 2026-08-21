<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures;

use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Laravel\Console\Concerns\RetriesRequests;

final class ClassWithRetriesRequests
{
    use RetriesRequests;

    public function configureRetries(int $maxRetries, int $retryDelay): void
    {
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }

    public function setApi(RequestCoordinator $api): void
    {
        $this->api = $api;
    }
}
