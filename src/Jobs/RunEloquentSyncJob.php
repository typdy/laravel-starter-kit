<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Typdy\StarterKit\Laravel\Console\EloquentSync;
use Typdy\StarterKit\Laravel\Webhooks\Contracts\InvalidatesNonDatabaseCache;
use Typdy\StarterKit\Typdy;

use function array_key_exists;
use function array_last;
use function is_string;

final class RunEloquentSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60, 120];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public array $options = [],
        public ?int $constructId = null,
        public string $domain = 'constructs',
    ) {}

    public function handle(ConsoleKernel $artisan, InvalidatesNonDatabaseCache $invalidator): void
    {
        // @mago-expect analysis:mixed-assignment
        $blueprint = $this->options['--blueprint'] ?? null;

        if (!is_string($blueprint) || $blueprint === '') {
            throw new RuntimeException('Webhook sync job requires a --blueprint option.');
        }

        $options = $this->options;
        $options['--no-interaction'] = true;

        if ($this->constructId !== null) {
            $options['--id'] = $this->constructId;
        }

        $exitCode = $artisan->call('typdy:eloquent:sync', $options);

        if ($exitCode === 0) {
            /** @var string $team */
            $team = $this->options['--team'] ?? Typdy::config()->team;
            /** @var string $project */
            $project = $this->options['--project'] ?? Typdy::config()->project;

            $invalidator->invalidate(
                team: $team,
                project: $project,
                domain: $this->domain,
                blueprint: $blueprint,
                constructId: $this->constructId,
            );

            return;
        }

        if ($exitCode === EloquentSync::EXIT_LOCK_BUSY) {
            $this->release($this->resolveBackoffSeconds());

            return;
        }

        throw new RuntimeException('Eloquent sync job failed: ' . $artisan->output());
    }

    private function resolveBackoffSeconds(): int
    {
        $attempt = $this->attempts();

        if ($attempt <= 1) {
            return $this->backoff[0] ?? 10;
        }

        $index = $attempt - 1;

        if (array_key_exists($index, $this->backoff)) {
            return (int) $this->backoff[$index];
        }

        $last = array_last($this->backoff);

        if ($last !== null) {
            return (int) $last;
        }

        return 120;
    }
}
