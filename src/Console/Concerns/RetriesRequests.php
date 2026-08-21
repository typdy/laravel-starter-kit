<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console\Concerns;

use RuntimeException;
use Throwable;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Parsers\Data\Document;

use function max;
use function usleep;

/**
 * @api
 */
trait RetriesRequests
{
    protected int $maxRetries = 3;

    protected int $retryDelay = 250_000;

    private RequestCoordinator $api;

    /**
     * @param array<string, mixed> $parameters
     *
     * @mago-expect analysis:unhandled-thrown-type
     */
    public function request(string $path, array $parameters = []): Document
    {
        $lastDocument = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $document = $this->api->request(
                    path: $path,
                    parameters: $parameters,
                    useMapi: true,
                );

                $lastDocument = $document;

                $status = $document->response->getStatusCode();

                if (!$this->isRetryableStatus($status) || $attempt === $this->maxRetries) {
                    return $document;
                }
            } catch (Throwable $e) {
                if ($attempt === $this->maxRetries) {
                    throw $e;
                }
            }

            usleep(max(0, $this->retryDelay * ($attempt + 1)));
        }

        if ($lastDocument !== null) {
            return $lastDocument;
        }

        throw new RuntimeException('No response received from typdy after retries.');
    }

    private function isRetryableStatus(int $status): bool
    {
        return $status === 429 || $status >= 500 && $status <= 599;
    }
}
