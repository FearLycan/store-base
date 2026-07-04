<?php

declare(strict_types=1);

namespace app\components\aliexpress;

/**
 * Marker for sync-job failures that will never succeed on retry — a permanent, deterministic
 * condition (product not in the affiliate program, invalid id, …) rather than a transient hiccup
 * (rate limit, timeout, network). The queue worker skips these terminally instead of burning the
 * full retry budget, so they don't clog the queue for hours before landing in `failed`.
 */
interface NonRetryableJobException
{
}
