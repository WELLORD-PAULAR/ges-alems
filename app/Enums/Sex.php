<?php

namespace App\Enums;

/**
 * The old app stored sex as an uppercase enum. New records keep the same
 * stored values so imports and exports stay comparable.
 */
enum Sex: string
{
    case Male = 'MALE';
    case Female = 'FEMALE';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $sex): array => [
                'value' => $sex->value,
                'label' => $sex->label(),
            ],
            self::cases(),
        );
    }
}
