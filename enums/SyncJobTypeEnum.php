<?php

declare(strict_types=1);

namespace app\enums;

enum SyncJobTypeEnum: string
{
    case STORE_DISCOVERY = 'store_discovery';
    case PRODUCT_DETAIL  = 'product_detail';
    case PRODUCT_REVIEWS = 'product_reviews';
    case PRICE_REFRESH   = 'price_refresh';
    case TITLE_REWRITE   = 'title_rewrite';

    /**
     * Queue ordering weight (lower = claimed first). Cheap "publish" work outranks the import
     * backlog so a freshly-imported draft goes live instead of starving behind thousands of
     * PRODUCT_DETAIL rows with lower ids. Ties break by id (FIFO) in {@see SyncJob::claimNext}.
     */
    public function queuePriority(): int
    {
        return match ($this) {
            self::TITLE_REWRITE   => 0, // draft -> active; drains the existing backlog first
            self::PRICE_REFRESH   => 1,
            self::PRODUCT_REVIEWS => 2,
            self::PRODUCT_DETAIL  => 3,
            self::STORE_DISCOVERY => 4,
        };
    }

    /**
     * Whether processing this job hits the AliExpress *affiliate* gateway (the one with the strict
     * per-second call limit). Only these need the inter-job rate-limit sleep in the worker;
     * TITLE_REWRITE (LLM) and PRODUCT_REVIEWS (mtop) go to other hosts and must not be throttled.
     */
    public function hitsAffiliateApi(): bool
    {
        return match ($this) {
            self::PRODUCT_DETAIL, self::PRICE_REFRESH => true,
            default => false,
        };
    }
}
