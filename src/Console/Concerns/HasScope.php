<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console\Concerns;

use Throwable;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Typdy;

/**
 * @api
 */
trait HasScope
{
    private RequestCoordinator $api;

    /**
     * @return never
     */
    abstract protected function fail(Throwable|string|null $exception = null);

    /**
     * @param string|null $key
     *
     * @return mixed
     */
    abstract protected function option($key = null);

    /**
     * @return list{string, string, ?string}
     */
    private function applyApiScope(): array
    {
        [$this->api->team, $this->api->project] = $this->getScope();

        return $this->getScope();
    }

    /**
     * @return list{string, string, ?string}
     */
    private function getScope(): array
    {
        return [
            (string) $this->option('team') ?: Typdy::config()->team,
            (string) $this->option('project') ?: Typdy::config()->project,
            (string) $this->option('blueprint') ?: null,
        ];
    }
}
