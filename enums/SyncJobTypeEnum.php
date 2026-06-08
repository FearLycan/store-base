<?php

declare(strict_types=1);

namespace app\enums;

enum SyncJobTypeEnum: string
{
    case STORE_DISCOVERY = 'store_discovery';
    case PRODUCT_DETAIL  = 'product_detail';
    case PRODUCT_REVIEWS = 'product_reviews';
    case PRICE_REFRESH   = 'price_refresh';
}
