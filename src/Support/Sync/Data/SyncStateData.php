<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Data;

final class SyncStateData
{
    /**
     * @param list<string> $supportedBlueprints Blueprints that the app has models for
     * @param array<string, list<string>> $includedConstructs Constructs that were included in the sync process, grouped by blueprint
     */
    public function __construct(
        public string $runDirectory,
        public array $supportedBlueprints,
        public array $includedConstructs = [],
        public GlobalSyncStateData $global = new GlobalSyncStateData(),
        public ConstructSyncStateData $construct = new ConstructSyncStateData(),
    ) {}

    /**
     * @param array{
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
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            runDirectory: $data['runDirectory'],
            supportedBlueprints: $data['supportedBlueprints'],
            includedConstructs: $data['includedConstructs'],
            global: GlobalSyncStateData::fromArray($data['global']),
            construct: ConstructSyncStateData::fromArray($data['construct']),
        );
    }

    /**
     * @return array{
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
     * }
     */
    public function toArray(): array
    {
        return [
            'runDirectory' => $this->runDirectory,
            'supportedBlueprints' => $this->supportedBlueprints,
            'includedConstructs' => $this->includedConstructs,
            'global' => $this->global->toArray(),
            'construct' => $this->construct->toArray(),
        ];
    }
}
