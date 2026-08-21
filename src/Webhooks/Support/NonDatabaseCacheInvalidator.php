<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Webhooks\Support;

use Override;
use Typdy\StarterKit\Laravel\Webhooks\Contracts\InvalidatesNonDatabaseCache;
use Typdy\StarterKit\Repositories\Contracts\Collection;
use Typdy\StarterKit\Repositories\Contracts\Replayable;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesRepositories;
use Typdy\StarterKit\Storage\Contracts\DatabaseDriver;
use Typdy\StarterKit\Storage\Contracts\InvalidatesRequests;
use Typdy\StarterKit\Typdy;

use function is_subclass_of;

final class NonDatabaseCacheInvalidator implements InvalidatesNonDatabaseCache
{
    #[Override]
    public function invalidate(
        string $team,
        string $project,
        string $domain,
        string $blueprint,
        ?int $constructId,
    ): void {
        $repos = Typdy::container(ResolvesRepositories::class)->resolveMany($team, $project);

        foreach ($repos as $repo) {
            if (!$this->isTargetRepo($repo, $domain, $blueprint)) {
                continue;
            }

            if (!$this->hasDatabaseDriver($repo)) {
                continue;
            }

            if (!$repo instanceof Replayable) {
                continue;
            }

            $requests = $repo->replayableRequests($constructId);

            foreach ($repo->getDrivers() as $driverClass) {
                if (is_subclass_of($driverClass, DatabaseDriver::class)) {
                    continue;
                }

                $driver = Typdy::container($driverClass);

                if (!$driver instanceof InvalidatesRequests) {
                    continue;
                }

                foreach ($requests as $request) {
                    $driver->invalidate($repo, $request);
                }
            }
        }
    }

    private function hasDatabaseDriver(Collection $repo): bool
    {
        foreach ($repo->getDrivers() as $driverClass) {
            if (is_subclass_of($driverClass, DatabaseDriver::class)) {
                return true;
            }
        }

        return false;
    }

    private function isTargetRepo(Collection $repo, string $domain, string $blueprint): bool
    {
        if ($domain === 'globals') {
            return $repo->isGlobal();
        }

        return !$repo->isGlobal() && $repo->getBlueprint() === $blueprint;
    }
}
