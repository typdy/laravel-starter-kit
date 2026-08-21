<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Console\Input\InputOption;
use Typdy\StarterKit\Api\RequestCoordinator;
use Typdy\StarterKit\Laravel\Console\Concerns\HasModelResolution;
use Typdy\StarterKit\Laravel\Console\Concerns\HasScope;
use Typdy\StarterKit\Laravel\Support\Migrations\Data\MigrationPlanData;
use Typdy\StarterKit\Laravel\Support\Migrations\ExistingMigrationScanner;
use Typdy\StarterKit\Laravel\Support\Migrations\MigrationPlanner;
use Typdy\StarterKit\Laravel\Support\Migrations\RelationshipPlanner;
use Typdy\StarterKit\Laravel\Support\Migrations\StubMigrationWriter;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Parsers\Data\Document;
use Typdy\StarterKit\Parsers\Exceptions\ResponseParserException;
use Typdy\StarterKit\Resolvers\Contracts\ResolvesModels;

use function array_map;
use function implode;
use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

final class EloquentMigrations extends Command
{
    use HasScope;
    use HasModelResolution;

    protected $name = 'typdy:eloquent:migrations';

    protected $description = 'Generate Eloquent migrations for typdy models.';

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     * @throws ResponseParserException
     */
    public function handle(ResolvesModels $modelResolver, RequestCoordinator $api): void
    {
        $this->modelResolver = $modelResolver;
        $this->api = $api;

        $this->applyApiScope();

        $models = $this->resolveModels();

        if ($models === []) {
            info('No migrations to create.');

            return;
        }

        $existing = $this->scanExistingMigrations();

        $blueprints = $this->fetchBlueprints($api, $this->resolveModelBlueprints());
        $plans = $this->buildPlans($models, $existing, $blueprints);

        if ($plans === []) {
            info('No migrations to create.');

            return;
        }

        if (!$this->confirmPlan($plans)) {
            note('Migration generation cancelled.');

            return;
        }

        $paths = $this->writePlans($plans);

        $this->showGeneratedMigrations($plans, $paths);
    }

    #[Override]
    protected function getOptions(): array
    {
        return [
            ['team',      null, InputOption::VALUE_REQUIRED, 'Team identifier to scope the sync.'],
            ['project',   null, InputOption::VALUE_REQUIRED, 'Project identifier to scope the sync.'],
            ['blueprint', null, InputOption::VALUE_REQUIRED, 'Blueprint identifier to scope the sync.'],
        ];
    }

    /**
     * @param array<int, Construct&Model> $models
     * @param array<string, list<string>> $existing
     *
     * @return list<MigrationPlanData>
     */
    private function buildPlans(array $models, array $existing, Document $blueprints): array
    {
        $migrationPlanner = new MigrationPlanner();
        $relationshipPlanner = new RelationshipPlanner();

        return [
            ...$migrationPlanner->plan($models, $existing, $blueprints),
            ...$relationshipPlanner->plan($models, $existing),
        ];
    }

    /**
     * @param list<MigrationPlanData> $plans
     */
    private function confirmPlan(array $plans): bool
    {
        callout(
            label: 'Migration Plan',
            content: 'The following migrations will be generated. Please review and confirm before proceeding.',
        );

        table(
            headers: ['Migration Name', 'Table', 'Type'],
            rows: array_map(
                static fn (MigrationPlanData $plan): array => [
                    $plan->migrationName(),
                    $plan->table,
                    $plan->schemaMethod(),
                ],
                $plans,
            ),
        );

        return confirm('Proceed with generating the above migrations?', default: true);
    }

    /**
     * @param array<int, string> $identifiers
     *
     * @throws JsonException
     * @throws ClientExceptionInterface
     * @throws ResponseParserException
     */
    private function fetchBlueprints(RequestCoordinator $api, array $identifiers): Document
    {
        return spin(
            callback: /**
             * @throws JsonException
             * @throws ClientExceptionInterface
             * @throws ResponseParserException
             */
            static fn (): Document => $api->request(
                path: 'blueprints',
                parameters: [
                    'include' => 'fields',
                    'filter[identifier]' => implode(',', $identifiers),
                    'all' => true,
                ],
                useMapi: true,
            ),
            message: 'Fetching blueprints from typdy...',
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function scanExistingMigrations(): array
    {
        return spin(
            callback: static fn () => new ExistingMigrationScanner()->scan(),
            message: 'Scanning existing migrations...',
        );
    }

    /**
     * @param list<MigrationPlanData> $plans
     * @param list<string> $paths
     */
    private function showGeneratedMigrations(array $plans, array $paths): void
    {
        info('Migrations generated successfully:');

        table(
            headers: ['Migration Name', 'Path'],
            rows: array_map(
                static fn (MigrationPlanData $plan, string $path): array => [
                    $plan->migrationName(),
                    $path,
                ],
                $plans,
                $paths,
            ),
        );
    }

    /**
     * @param list<MigrationPlanData> $plans
     *
     * @return list<string>
     */
    private function writePlans(array $plans): array
    {
        $writer = new StubMigrationWriter();

        return spin(
            callback: static fn () => array_map($writer->write(...), $plans),
            message: 'Writing migration files...',
        );
    }
}
