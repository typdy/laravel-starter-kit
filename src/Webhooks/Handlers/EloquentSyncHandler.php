<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Webhooks\Handlers;

use Override;
use Typdy\StarterKit\Laravel\Jobs\RunEloquentSyncJob;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Webhooks\Contracts\Handler;
use Typdy\StarterKit\Webhooks\Data\Result;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;

use function dispatch;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function now;

final class EloquentSyncHandler implements Handler
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * @var array<string, list<string>>
     */
    private array $supported = [
        'constructs' => ['create', 'update', 'delete'],
        'globals' => ['create', 'update', 'delete'],
    ];

    #[Override]
    public function getName(): string
    {
        return 'eloquent-sync';
    }

    #[Override]
    public function handle(Payload $payload, ResultSet $results): void
    {
        $domain = $payload->getDomain()->value;
        $event = $payload->getEvent()->value;

        $supportedEvents = $this->supported[$domain] ?? [];

        if (!in_array($event, $supportedEvents, strict: true)) {
            $results->add(new Result("No action taken for event: {$domain}.{$event}."));

            return;
        }

        $options = [];
        $options['--team'] = $this->options['team'] ?? Typdy::config()->team;
        $options['--project'] = $this->options['project'] ?? Typdy::config()->project;

        $blueprint = $payload->getBlueprint();

        if ($blueprint === null) {
            $results->add(new Result(
                "Webhook payload for {$domain}.{$event} did not include a blueprint identifier.",
                failed: true,
            ));

            return;
        }

        $options['--blueprint'] = $blueprint;

        // the current supported domains (constructs, globals) will always
        //  include a construct id in the payload
        $constructId = $payload->payload?->construct->id ?? null;
        $constructId = $constructId !== null ? (int) $constructId : null;

        $job = new RunEloquentSyncJob($options, $constructId, $domain);

        // @mago-expect analysis:mixed-assignment
        $tries = $this->options['tries'] ?? null;

        if (is_int($tries) && $tries > 0) {
            $job->tries = $tries;
        }

        // @mago-expect analysis:mixed-assignment
        $backoff = $this->options['backoff'] ?? null;

        if (is_array($backoff)) {
            $backoff = array_values(array_filter(
                $backoff,
                static fn (mixed $value): bool => is_int($value) && $value > 0,
            ));

            if ($backoff !== []) {
                /** @var list<int> $backoff */
                $job->backoff = $backoff;
            }
        }

        // @mago-expect analysis:mixed-assignment
        $delaySeconds = $this->options['delay'] ?? 5;

        if (!is_int($delaySeconds) || $delaySeconds < 0) {
            $delaySeconds = 5;
        }

        $pending = dispatch($job)->delay(now()->addSeconds($delaySeconds));

        // @mago-expect analysis:mixed-assignment
        $queue = $this->options['queue'] ?? null;

        if (is_string($queue) && $queue !== '') {
            $pending->onQueue($queue);
        }

        $results->add(new Result("Queued eloquent sync for {$domain}.{$event}: {$blueprint}."));
    }

    #[Override]
    public function withOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }
}
