<?php

namespace App\Support\Legacy;

/**
 * Row counts for one import run. `legacy` is what was read from the old
 * database, `imported` is what now exists in the new one.
 */
final class LegacyImportResult
{
    /**
     * @param  array<string, array{legacy: int, imported: int}>  $counts
     */
    public function __construct(
        public readonly array $counts,
        public readonly LegacyImportReport $report,
        public readonly string $reportPath,
        public readonly bool $dryRun,
    ) {}
}
