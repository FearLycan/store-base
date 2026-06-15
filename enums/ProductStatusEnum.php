<?php

declare(strict_types=1);

namespace app\enums;

enum ProductStatusEnum: string
{
    case DRAFT        = 'draft';
    case ACTIVE       = 'active';
    case INACTIVE     = 'inactive';
    case OUT_OF_STOCK = 'out_of_stock';
}
