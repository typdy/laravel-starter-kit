<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Controllers;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Typdy\StarterKit\Typdy;
use Typdy\StarterKit\Webhooks\Contracts\Handler;
use Typdy\StarterKit\Webhooks\Exceptions\InvalidSigningKey;
use Typdy\StarterKit\Webhooks\Payload;
use Typdy\StarterKit\Webhooks\ResultSet;
use Typdy\StarterKit\Webhooks\WebhookCoordinator;

use function app;
use function array_key_exists;
use function config;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function request;
use function response;

final readonly class WebhooksController
{
    public function __construct(
        private WebhookCoordinator $webhooks,
    ) {}

    /**
     * @param array<string, list<string|null>> $headers
     *
     * @return array<string, string>
     */
    private function collapseHeaders(array $headers): array
    {
        $collapsed = [];

        foreach ($headers as $key => $values) {
            $collapsed[$key] = implode(', ', $values);

            if ($key === 'signature') {
                $collapsed['Signature'] = $collapsed[$key];
            }
        }

        return $collapsed;
    }

    /**
     * @param array<string, array<string, ResultSet>> $results
     *
     * @return array{
     *     status: string,
     *     results: list<array{
     *         webhook: string,
     *         handler: string,
     *         status: string,
     *         message: string,
     *     }>
     * }
     */
    private function prepareResults(array $results): array
    {
        $prepared = [
            'status' => 'success',
            'results' => [],
        ];

        foreach ($results as $name => $handled) {
            foreach ($handled as $handlerName => $resultSet) {
                foreach ($resultSet->results as $result) {
                    if ($result->failed) {
                        $prepared['status'] = 'failed';
                    }

                    $prepared['results'][] = [
                        'webhook' => $name,
                        'handler' => $handlerName,
                        'status' => $result->failed ? 'failed' : 'success',
                        'message' => $result->message,
                    ];
                }
            }
        }

        return $prepared;
    }

    /**
     * @param array<class-string<Handler>|int, array<string, mixed>|class-string<Handler>> $handlers
     */
    private function registerHandlersForWebhook(string $name, array $handlers, string $team, string $project): void
    {
        foreach ($handlers as $key => $value) {
            $class = null;
            $options = [];

            if (is_string($key)) {
                $class = $key;
                $options = is_array($value) ? $value : [];
            }

            if (is_int($key) && is_string($value)) {
                $class = $value;
            }

            if ($class === null) {
                continue;
            }

            $handler = app($class);

            // @mago-expect analysis:impossible-condition
            if (!$handler instanceof Handler) {
                throw new RuntimeException("Webhook handler '{$class}' must implement the Handler interface.");
            }

            $handler = $handler->withOptions([...$options, 'team' => $team, 'project' => $project]);

            $this->webhooks->registerHandler($name, $handler);
        }
    }

    public function __invoke(string $name): JsonResponse
    {
        /**
         * @var array<string, array{
         *     team: ?string,
         *     project: ?string,
         *     secret: ?string,
         *     handlers: array<class-string<Handler>|int, array<string, mixed>|class-string<Handler>>,
         * }> $config
         */
        $config = config('typdy.webhooks', []);

        if (!array_key_exists($name, $config)) {
            return response()->json([
                'status' => 'failed',
                'results' => [
                    'status' => 'failed',
                    'message' => "Webhook '{$name}' is not configured.",
                ],
            ]);
        }

        $secret = $config[$name]['secret'];

        if (!is_string($secret)) {
            throw new RuntimeException("Webhook secret is not present for the '{$name}' webhook.");
        }

        $this->registerHandlersForWebhook(
            $name,
            $config[$name]['handlers'],
            $config[$name]['team'] ?? Typdy::config()->team,
            $config[$name]['project'] ?? Typdy::config()->project,
        );

        try {
            $results = $this->webhooks->handle(
                Payload::make(
                    $name,
                    $secret,
                    request()->getContent(),
                    $this->collapseHeaders(request()->headers->all()),
                ),
            );
        } catch (InvalidSigningKey $e) {
            return response()->json([
                'status' => 'failed',
                'results' => [
                    'status' => 'failed',
                    'message' => 'Invalid signing key.',
                ],
            ]);
        }

        return response()->json($this->prepareResults($results));
    }
}
