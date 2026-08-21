<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console\Concerns;

use Typdy\StarterKit\Laravel\Support\Sync\Data\SyncStateData;
use Typdy\StarterKit\Laravel\Support\Sync\StateStore;

/**
 * @api
 */
trait HasSyncState
{
    private StateStore $store;

    public function getStore(): StateStore
    {
        return $this->store;
    }

    /**
     * @param string|null $key
     *
     * @return mixed
     */
    abstract protected function option($key = null);

    abstract private function getLockName(string $team, string $project, ?string $blueprint): string;

    /**
     * @return list{string, string, ?string}
     */
    abstract private function getScope(): array;

    /**
     * @param list<string> $blueprints
     */
    private function initializeSyncState(array $blueprints): SyncStateData
    {
        // @mago-expect analysis:mixed-operand
        $this->store = new StateStore($this->getLockName(...$this->getScope()), (bool) $this->option('resume'));

        return $this->store->initialize($blueprints);
    }
}
