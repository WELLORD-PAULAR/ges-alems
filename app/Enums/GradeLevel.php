<?php

namespace App\Enums;

/**
 * Canonical grade levels. The old app was inconsistent:
 *
 * - enrollments stored `Kindergart` or `1`..`6`
 * - the sectioning screen used `K`
 * - section CRUD used `KINDERGARTEN` and `GRADE 1`..`GRADE 6`
 *
 * Everything new uses `K` and `1`..`6`; `fromLegacy()` maps the old spellings.
 */
enum GradeLevel: string
{
    case Kindergarten = 'K';
    case Grade1 = '1';
    case Grade2 = '2';
    case Grade3 = '3';
    case Grade4 = '4';
    case Grade5 = '5';
    case Grade6 = '6';

    public function label(): string
    {
        return $this === self::Kindergarten
            ? 'Kindergarten'
            : 'Grade '.$this->value;
    }

    /**
     * Kindergarten sorts before Grade 1.
     */
    public function sortOrder(): int
    {
        return $this === self::Kindergarten ? 0 : (int) $this->value;
    }

    public function isKindergarten(): bool
    {
        return $this === self::Kindergarten;
    }

    /**
     * Normalize any spelling the old app (or a human) might have produced.
     */
    public static function fromLegacy(?string $value): ?self
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['K', 'KINDERGARTEN', 'KINDERGART'], true)) {
            return self::Kindergarten;
        }

        if (preg_match('/^(?:GRADE\s*)?([1-6])$/', $normalized, $matches) === 1) {
            return self::tryFrom($matches[1]);
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string, sort_order: int}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $grade): array => [
                'value' => $grade->value,
                'label' => $grade->label(),
                'sort_order' => $grade->sortOrder(),
            ],
            self::cases(),
        );
    }
}
