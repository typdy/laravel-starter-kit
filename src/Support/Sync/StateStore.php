<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync;

use GuzzleHttp\Psr7\Response;
use JsonException;
use Typdy\StarterKit\Laravel\Support\Sync\Data\SyncStateData;
use Typdy\StarterKit\Parsers\Contracts\ResponseParser;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Typdy;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function rmdir;
use function scandir;
use function str_ends_with;
use function str_starts_with;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class StateStore
{
    private string $statePath;

    private string $runDirectory;

    public function __construct(
        private string $lockName,
        private bool $resume,
    ) {
        $this->statePath = $this->getBasePath() . '/' . $this->lockName . '.json';
        $this->runDirectory = $this->getBasePath() . '/' . $this->lockName;
    }

    public function clear(): void
    {
        if (file_exists($this->statePath)) {
            unlink($this->statePath);
        }

        $this->deleteDirectory($this->runDirectory);
    }

    /**
     * @return list<Document>
     *
     * @throws JsonException
     * @throws DecodingException
     * @throws ResponseParserException
     */
    public function getDocuments(SyncStateData $state): array
    {
        $runDirectory = $state->runDirectory;

        $documents = [];

        $globalPath = $runDirectory . '/globals/global.json';

        if (file_exists($globalPath)) {
            $response = new Response(
                status: 200,
                body: file_get_contents($globalPath) ?: '',
            );

            $parser = Typdy::container()->make(ResponseParser::class, ['mixedType' => true]);

            $documents[] = $parser->parse($response);
        }

        $constructsRoot = $runDirectory . '/constructs';

        if (!is_dir($constructsRoot)) {
            return $documents;
        }

        foreach (scandir($constructsRoot) ?: [] as $blueprint) {
            if ($blueprint === '.' || $blueprint === '..') {
                continue;
            }

            $constructsPath = "{$constructsRoot}/{$blueprint}";

            if (!is_dir($constructsPath)) {
                continue;
            }

            $files = array_filter(
                scandir($constructsPath) ?: [],
                static fn (string $file): bool => str_starts_with($file, 'page-') && str_ends_with($file, '.json'),
            );

            foreach ($files as $file) {
                $response = new Response(
                    status: 200,
                    body: file_get_contents("{$constructsPath}/{$file}") ?: '',
                );

                $parser = Typdy::container()->make(ResponseParser::class, ['expectedType' => $blueprint]);

                $documents[] = $parser->parse($response);
            }
        }

        return $documents;
    }

    /**
     * @param list<string> $blueprints
     */
    public function initialize(array $blueprints): SyncStateData
    {
        if (!$this->resume) {
            $this->clear();
        }

        if (!file_exists($this->statePath)) {
            return $this->save(new SyncStateData($this->runDirectory, $blueprints));
        }

        /**
         * @var array{
         *     runDirectory: string,
         *     supportedBlueprints: list<string>,
         *     includedConstructs: array<string, list<string>>,
         *     global: array{
         *         blueprints: list<string>,
         *         failed: list<string>,
         *         completed: bool,
         *     },
         *     construct: array{
         *         blueprintPages: array<string, int>,
         *         fetchedPages: array<string, list<int>>,
         *         constructsCount: array<string, int>,
         *         blueprintFailures: array<string, int>,
         *     },
         * }|null $previousState
         */
        $previousState = json_decode(
            file_get_contents($this->statePath) ?: '',
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!is_array($previousState)) {
            $this->clear();

            return $this->initialize($blueprints);
        }

        return SyncStateData::fromArray($previousState);
    }

    public function save(SyncStateData $state): SyncStateData
    {
        $this->ensureDirectory(dirname($this->statePath));

        file_put_contents(
            $this->statePath,
            json_encode($state->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        return $state;
    }

    public function storeGlobalData(Document $document): void
    {
        $directory = $this->runDirectory . '/globals';

        $this->ensureDirectory($directory);

        $payload = (string) $document->response->getBody();

        file_put_contents($directory . '/global.json', $payload);
    }

    public function storePageData(string $blueprint, int $page, Document $document): void
    {
        $directory = $this->runDirectory . '/constructs/' . $blueprint;

        $this->ensureDirectory($directory);

        $payload = (string) $document->response->getBody();

        file_put_contents($directory . '/page-' . $page . '.json', $payload);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;

            if (is_dir($entryPath)) {
                $this->deleteDirectory($entryPath);

                continue;
            }

            unlink($entryPath);
        }

        rmdir($path);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, permissions: 0o755, recursive: true);
        }
    }

    private function getBasePath(): string
    {
        return Typdy::config()->privateStoragePath . '/sync';
    }
}
