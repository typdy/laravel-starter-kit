<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Feature\Controllers\Fixtures;

use Typdy\StarterKit\Webhooks\Contracts\Handler;
use Typdy\StarterKit\Webhooks\Data\Result;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;

final class DummyWebhookHandler implements Handler
{
    public function getName(): string
    {
        return 'dummy-webhook-handler';
    }

    public function handle(Payload $payload, ResultSet $results): void
    {
        $results->add(new Result("Handled {$payload->name}"));
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
