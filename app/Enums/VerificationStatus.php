<?php

namespace App\Enums;

/**
 * The seven verification states used by the old AMS `enrollment2.verification`
 * column. Values are stored exactly as the old app stored them so a legacy
 * import can map 1:1.
 */
enum VerificationStatus: string
{
    case Processing = 'PROCESSING';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
    case Withdrawn = 'WITHDRAWN';
    case TransferredIn = 'TRANSFERRED_IN';
    case TransferredOut = 'TRANSFERRED_OUT';
    case Dropped = 'DROPPED';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
            self::TransferredIn => 'Transferred In',
            self::TransferredOut => 'Transferred Out',
            self::Dropped => 'Dropped',
        };
    }

    /**
     * Badge tone used by the Vue UI.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Processing => 'warning',
            self::Verified => 'success',
            self::Rejected, self::Dropped => 'danger',
            self::Withdrawn => 'neutral',
            self::TransferredIn, self::TransferredOut => 'info',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Processing;
    }

    /**
     * The old app treated a blank or "PENDING" status as pending. Unknown
     * values also fall back to Processing so a dirty legacy row can never
     * break a page.
     */
    public static function normalize(?string $value): self
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '' || $normalized === 'PENDING') {
            return self::Processing;
        }

        return self::tryFrom($normalized) ?? self::Processing;
    }

    /**
     * Provisional transition table. This must be confirmed with the team
     * before Slice 4 (verification workflow) is built.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Processing => [self::Verified, self::Rejected, self::Withdrawn, self::Dropped],
            self::Verified => [self::Withdrawn, self::TransferredOut, self::Dropped],
            self::Rejected => [self::Processing, self::Withdrawn],
            self::Withdrawn => [self::Processing],
            self::TransferredIn => [self::Verified, self::Withdrawn, self::TransferredOut, self::Dropped],
            self::TransferredOut => [self::Processing],
            self::Dropped => [self::Processing],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'tone' => $status->tone(),
            ],
            self::cases(),
        );
    }
}
