<?php

namespace App\Console\Commands;

use App\Support\Legacy\LegacyDumpLoader;
use App\Support\Legacy\LegacyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportLegacy extends Command
{
    protected $signature = 'ams:import-legacy
                            {--dry-run : Run the whole import in a transaction and roll it back}
                            {--dump= : Load this MySQL dump into the legacy connection before importing}
                            {--report= : Path for the JSON reject report}';

    protected $description = 'Import the old AMS database into the new schema (safe to re-run).';

    public function handle(LegacyDumpLoader $loader, LegacyImporter $importer): int
    {
        $dump = $this->option('dump');

        if (is_string($dump) && $dump !== '') {
            $path = is_file($dump) ? $dump : base_path($dump);

            if (! is_file($path)) {
                $this->components->error("Dump not found: {$path}");

                return self::FAILURE;
            }

            try {
                $this->components->info("Loading {$path} into the legacy connection...");
                $loader->load($path);
            } catch (Throwable $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if (! Schema::connection('legacy')->hasTable('enrollment2')) {
            $this->components->error('The legacy connection has no enrollment2 table.');
            $this->line('Load the dump first:');
            $this->line('  php artisan ams:load-legacy-dump "legacy/ams/gem_db (1).sql"');
            $this->line('or pass --dump="legacy/ams/gem_db (1).sql".');

            return self::FAILURE;
        }

        $report = $this->option('report');
        $reportPath = is_string($report) && $report !== '' ? $report : null;

        try {
            $result = $importer->import((bool) $this->option('dry-run'), $reportPath);
        } catch (Throwable $exception) {
            $this->components->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Legacy rows', 'Imported'],
            collect($result->counts)
                ->map(fn (array $count, string $table): array => [
                    $table,
                    number_format($count['legacy']),
                    number_format($count['imported']),
                ])
                ->values()
                ->all(),
        );

        if (($result->counts['sections']['legacy'] ?? null) === 0) {
            $this->line('Sections are seeded from config/school.php; the old database has no sections table.');
        }

        $this->components->info(
            ($result->dryRun ? 'Dry run complete; all changes rolled back.' : 'Import complete.')
            .' Report: '.$result->reportPath,
        );

        $rejects = count($result->report->rejects());
        $warnings = count($result->report->warnings());

        if ($rejects > 0) {
            $this->components->warn("{$rejects} row(s) were rejected. See the report for details.");
        }

        if ($warnings > 0) {
            $this->components->warn("{$warnings} warning(s) were written to the report.");
        }

        return self::SUCCESS;
    }
}
