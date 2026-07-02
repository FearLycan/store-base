<?php

declare(strict_types=1);

namespace app\enums;

enum CategoryStatusEnum: string
{
    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';

    /** Human-readable label for admin badges. */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE   => 'active',
            self::INACTIVE => 'inactive',
        };
    }

    /** Bootstrap 5 contextual class for the status badge. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE   => 'text-bg-success',
            self::INACTIVE => 'text-bg-secondary',
        };
    }
}
