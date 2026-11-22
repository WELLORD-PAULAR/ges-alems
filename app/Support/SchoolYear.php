<?php

namespace App\Support;

/**
 * School years are stored as `YYYY-YYYY`. The old database contains several
 * dirty spellings (`2026 _2027`, `2026 - 202`, `2025_2026`), so every import
 * and form request goes through this normalizer.
 */
final class SchoolYear
{
    public static function current(): string
    {
        return (string) config('school.current_school_year');
    }

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        /** @var list<string> $years */
        $years = config('school.school_years', []);

        return $years;
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{4}$/', $value) === 1;
    }

    /**
     * Normalize any spelling that contains a start year.
     *
     * `2026 _2027`, `2026 - 202`, `2025_2026` and `2026` all resolve to a
     * valid `YYYY-YYYY` value. Anything without a four-digit year is null.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d{4})\s*[-_ ]\s*(\d{2,4})/', $value, $matches) === 1) {
            $start = (int) $matches[1];

            return $start.'-'.($start + 1);
        }

        if (preg_match('/^(\d{4})$/', $value, $matches) === 1) {
            $start = (int) $matches[1];

            return $start.'-'.($start + 1);
        }

        return null;
    }
}
