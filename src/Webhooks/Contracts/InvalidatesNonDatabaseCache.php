<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Webhooks\Contracts;

/**
 * @api
 */
interface InvalidatesNonDatabaseCache
{
    public function invalidate(
        string $team,
        string $project,
        string $domain,
        string $blueprint,
        ?int $constructId,
    ): void;
}
