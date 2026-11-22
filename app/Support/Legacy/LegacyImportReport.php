<?php

namespace App\Support\Legacy;

use RuntimeException;

/**
 * Collects every row that could not be imported and every warning (duplicates,
 * unknown sections, dirty identifiers) so the command can write a review file.
 */
final class LegacyImportReport
{
    /**
     * @var list<array{table: string, key: string, reason: string}>
     */
    private array $rejects = [];

    /**
     * @var list<array{table: string, key: string, reason: string}>
     */
    private array $warnings = [];

    public function reject(string $table, string|int $key, string $reason): void
    {
        $this->rejects[] = [
            'table' => $table,
            'key' => (string) $key,
            'reason' => $reason,
        ];
    }

    public function warn(string $table, string|int $key, string $reason): void
    {
        $this->warnings[] = [
            'table' => $table,
            'key' => (string) $key,
            'reason' => $reason,
        ];
    }

    /**
     * @return list<array{table: string, key: string, reason: string}>
     */
    public function rejects(): array
    {
        return $this->rejects;
    }

    /**
     * @return list<array{table: string, key: string, reason: string}>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function write(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create report directory: {$directory}");
        }

        $payload = json_encode([
            'generated_at' => now()->toIso8601String(),
            'rejects' => $this->rejects,
            'warnings' => $this->warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($payload === false || file_put_contents($path, $payload) === false) {
            throw new RuntimeException("Could not write import report: {$path}");
        }
    }
}
