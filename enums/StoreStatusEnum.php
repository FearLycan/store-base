<?php

declare(strict_types=1);

namespace app\enums;

enum StoreStatusEnum: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
}
