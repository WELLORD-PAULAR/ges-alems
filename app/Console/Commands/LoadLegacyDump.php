<?php

namespace App\Console\Commands;

use App\Support\Legacy\LegacyDumpLoader;
use Illuminate\Console\Command;
use Throwable;

class LoadLegacyDump extends Command
{
    protected $signature = 'ams:load-legacy-dump
                            {dump? : Path to the MySQL dump (defaults to legacy/ams/gem_db (1).sql)}
                            {--connection=legacy : Database connection to load into}';

    protected $description = 'Load the old AMS MySQL dump into the local legacy connection.';

    public function handle(LegacyDumpLoader $loader): int
    {
        $argument = $this->argument('dump');
        $path = is_string($argument) && $argument !== ''
            ? $argument
            : base_path('legacy/ams/gem_db (1).sql');

        if (! is_file($path)) {
            $this->components->error("Dump not found: {$path}");
            $this->line('Pass the path explicitly: php artisan ams:load-legacy-dump "path/to/gem_db.sql"');

            return self::FAILURE;
        }

        $connection = $this->option('connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : 'legacy';

        try {
            $counts = $loader->load($path, $connection);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Loaded {$path} into the [{$connection}] connection.");

        $this->table(
            ['Table', 'Rows'],
            collect($counts)
                ->map(fn (int $rows, string $table): array => [$table, number_format($rows)])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }
}
