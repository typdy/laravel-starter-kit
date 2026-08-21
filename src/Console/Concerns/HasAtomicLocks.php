<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console\Concerns;

use Illuminate\Cache\Lock;

use function cache;
use function Laravel\Prompts\info;

/**
 * @api
 */
trait HasAtomicLocks
{
    private ?Lock $lock = null;

    private int $lockTtl = 3600;

    public function releaseLock(bool $force = false): void
    {
        if ($force) {
            $this->lock?->forceRelease();

            return;
        }

        $this->lock?->release();
    }

    /**
     * @param string|null $key
     *
     * @return mixed
     */
    abstract protected function option($key = null);

    /**
     * @return list{string, string, ?string}
     */
    abstract private function getScope(): array;

    private function acquireLock(): bool
    {
        $name = $this->getLockName(...$this->getScope());

        // @mago-expect analysis:mixed-operand
        $force = (bool) $this->option('force');

        if ($force) {
            info('Force option enabled. Bypassing lock check.');
        }

        /** @var Lock $lock */
        $lock = cache()->lock($name, $this->lockTtl);

        if ($force) {
            $lock->forceRelease();
        }

        if (!$lock->get()) {
            info(
                'Another typdy sync is already running, or has crashed. Re-run with --force to continue if you are certain that no other sync is running.',
            );

            $this->lock = null;

            return false;
        }

        $this->lock = $lock;

        return true;
    }

    private function getLockName(string $team, string $project, ?string $blueprint): string
    {
        $blueprint ??= '*';

        return "typdy:eloquent:sync:{$team}:{$project}:{$blueprint}";
    }

    private function hasLock(): bool
    {
        return $this->lock !== null;
    }
}
