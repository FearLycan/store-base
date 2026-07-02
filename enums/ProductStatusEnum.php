<?php

declare(strict_types=1);

namespace app\enums;

enum ProductStatusEnum: string
{
    case DRAFT        = 'draft';
    case ACTIVE       = 'active';
    case INACTIVE     = 'inactive';
    case OUT_OF_STOCK = 'out_of_stock';

    /** Human-readable label for admin badges. */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT        => 'draft',
            self::ACTIVE       => 'active',
            self::INACTIVE     => 'inactive',
            self::OUT_OF_STOCK => 'out of stock',
        };
    }

    /** Bootstrap 5 contextual class for the status badge. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE       => 'text-bg-success',
            self::INACTIVE     => 'text-bg-secondary',
            self::DRAFT        => 'text-bg-warning',
            self::OUT_OF_STOCK => 'text-bg-danger',
        };
    }
}
