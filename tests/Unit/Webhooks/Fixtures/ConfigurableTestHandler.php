<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Webhooks\Fixtures;

use Typdy\StarterKit\Webhooks\Contracts\Handler;
use Typdy\StarterKit\Webhooks\Data\Result;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ConfigurableTestHandler implements Handler
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    public function getName(): string
    {
        return 'configurable-test-handler';
    }

    public function handle(Payload $payload, ResultSet $results): void
    {
        $results->add(new Result((string) json_encode($this->options, JSON_THROW_ON_ERROR)));
    }

    public function withOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }
}
