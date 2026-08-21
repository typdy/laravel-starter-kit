<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Transformers;

use JsonException;
use Typdy\StarterKit\Laravel\Console\EloquentSync;
use Typdy\StarterKit\Laravel\Support\Sync\Data\ContentData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\RelationshipEdgeData;
use Typdy\StarterKit\Laravel\Support\Sync\Data\SyncStateData;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Data\Resource;
use Typdy\StarterKit\Parsers\Exceptions\DecodingException;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Utils\Arr;
use Typdy\StarterKit\Utils\Str;

use function count;
use function in_array;
use function ksort;
use function Laravel\Prompts\progress;
use function sprintf;
use function usort;

final readonly class ContentTransformer
{
    public function __construct(
        private EloquentSync $command,
    ) {}

    /**
     * @throws JsonException
     * @throws DecodingException
     * @throws ResponseParserException
     */
    public function transform(SyncStateData $state): ContentData
    {
        $content = new ContentData();

        $documents = $this->command->getStore()->getDocuments($state);

        if ($documents === []) {
            return $content;
        }

        $docCount = count($documents);
        $currentDoc = 0;

        progress(
            label: 'Transforming constructs for persistence...',
            steps: $documents,
            callback: function (Document $document, $progress) use ($content, $state, $docCount, $currentDoc): void {
                $currentDoc++;

                $progress->hint("Transformed page {$currentDoc} of {$docCount}...");

                $this->transformDocument($document, $content);
            },
        );

        ksort($content->resourcesByType);

        usort(
            $content->relationships,
            static fn (RelationshipEdgeData $a, RelationshipEdgeData $b): int => $a->sourceKey <=> $b->sourceKey,
        );

        return $content;
    }

    private function makeEdgeKey(string $sourceKey, string $relationship, string $targetKey): string
    {
        return sprintf('%s|%s|%s', $sourceKey, $relationship, $targetKey);
    }

    private function makeResourceKey(Resource $resource): string
    {
        return $resource->type . ':' . $resource->id;
    }

    private function registerResource(Resource $resource, ContentData $content): void
    {
        $resourceKey = $this->makeResourceKey($resource);

        if (!in_array($resourceKey, $content->seenResources, strict: true)) {
            $content->seenResources[] = $resourceKey;
            $content->resourcesByType[$resource->type][$resource->id] = $resource;
        }

        foreach ($resource->relationships as $name => $relation) {
            $name = Str::camel($name);

            /** @var list<Resource> $relationships */
            $relationships = Arr::wrap($relation->data ?? []);

            foreach ($relationships as $target) {
                $targetKey = $this->makeResourceKey($target);
                $edgeKey = $this->makeEdgeKey($resourceKey, $name, $targetKey);

                if (in_array($edgeKey, $content->seenRelationships, strict: true)) {
                    continue;
                }

                $content->seenRelationships[] = $edgeKey;
                $content->relationships[] = new RelationshipEdgeData($resourceKey, $name, $targetKey);
            }
        }
    }

    private function transformDocument(Document $document, ContentData $content): void
    {
        /** @var list<Resource> $relationships */
        $relationships = Arr::wrap($document->data ?? []);

        foreach ($relationships as $resource) {
            $this->registerResource($resource, $content);
        }

        foreach ($document->included as $resource) {
            $this->registerResource($resource, $content);
        }
    }
}
