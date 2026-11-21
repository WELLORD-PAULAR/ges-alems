<?php

namespace App\Enums;

/**
 * DepEd three-term calendar plus the Summer label from the old config.
 *
 * Teacher assignments only accept terms 1..3; Summer is a label used by the
 * school calendar, not an assignable term.
 */
enum Term: int
{
    case First = 1;
    case Second = 2;
    case Third = 3;
    case Summer = 4;

    public function label(): string
    {
        return match ($this) {
            self::First => '1st Term (June - August)',
            self::Second => '2nd Term (September - November)',
            self::Third => '3rd Term (December - April)',
            self::Summer => 'Summer',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::First => '1st',
            self::Second => '2nd',
            self::Third => '3rd',
            self::Summer => 'Summer',
        };
    }

    public function months(): string
    {
        return match ($this) {
            self::First => 'June - August',
            self::Second => 'September - November',
            self::Third => 'December - April',
            self::Summer => 'Summer',
        };
    }

    public function isAssignable(): bool
    {
        return $this !== self::Summer;
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $term): bool => $term->isAssignable(),
        ));
    }
}
