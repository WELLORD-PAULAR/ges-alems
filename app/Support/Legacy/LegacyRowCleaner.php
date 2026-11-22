<?php

namespace App\Support\Legacy;

use Illuminate\Support\Carbon;

/**
 * Cleaning rules for dirty legacy values. Every rule lives here so the
 * importer and the tests use the same behavior.
 */
final class LegacyRowCleaner
{
    /**
     * Trim text and turn empty strings into null.
     */
    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * MySQL zero dates (`0000-00-00`) become null.
     */
    public static function date(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * Same as date(), but for timestamp columns.
     */
    public static function dateTime(mixed $value): ?Carbon
    {
        $value = self::text($value);

        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Split a MySQL SET column (comma separated) into a clean JSON array.
     *
     * @return list<string>
     */
    public static function set(mixed $value): array
    {
        $value = self::text($value);

        if ($value === null) {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    public static function boolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * Collapse whitespace in names without changing the old casing.
     */
    public static function name(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    /**
     * Keep only digits. Used for LRNs and other numeric identifiers.
     */
    public static function digitsOnly(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * PSA/BCN numbers are dirty in the old data: `N/A`, `00000`, spaced and
     * dashed formats. Drop non-answers but keep the original formatting of
     * real values for traceability.
     */
    public static function psaBcn(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        if (in_array(strtoupper($value), ['NO', 'N/A', 'NA', 'NONE', '-'], true)) {
            return null;
        }

        $digits = self::digitsOnly($value);

        if ($digits === null || trim($digits, '0') === '') {
            return null;
        }

        return $value;
    }

    /**
     * Learner Reference Numbers are 12 digits. Keep the digits we have; the
     * importer warns when the length is not 12.
     */
    public static function lrn(mixed $value): ?string
    {
        return self::digitsOnly($value);
    }

    /**
     * 4Ps household numbers are free text in the old data (`#553`, `NO`,
     * `N/A`). Keep real numbers, drop non-answers and all-zero placeholders.
     */
    public static function fourPsHouseholdNumber(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        if (in_array(strtoupper($value), ['NO', 'N/A', 'NA', 'NONE', '-'], true)) {
            return null;
        }

        $value = trim(ltrim($value, '# '));

        if ($value === '' || trim($value, '0') === '') {
            return null;
        }

        return $value;
    }

    /**
     * Philippine ZIP codes are four digits. The old app stored `0` as its
     * empty value.
     */
    public static function zipCode(mixed $value): ?string
    {
        $digits = self::digitsOnly($value);

        if ($digits === null || trim($digits, '0') === '') {
            return null;
        }

        return str_pad($digits, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Normalize the old uppercase sex enum.
     */
    public static function sex(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, ['MALE', 'FEMALE'], true) ? $value : null;
    }
}
