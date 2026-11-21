<?php

namespace App\Enums;

/**
 * Decision D5: only Admin and Teacher exist. The old PARENT role is dropped
 * because it bounced between the two dashboards forever.
 */
enum Role: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Teacher => 'Teacher',
        };
    }

    /**
     * Where this role lands after login. The routes are created in Slice 2.
     */
    public function homePath(): string
    {
        return match ($this) {
            self::Admin => '/admin/dashboard',
            self::Teacher => '/teacher/dashboard',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            self::cases(),
        );
    }
}
