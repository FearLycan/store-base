<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use RuntimeException;

/**
 * Thrown when a product's authoritative Affiliate `shop_id` does not match the store it was queued
 * under — i.e. the store listing smuggled in an item that actually belongs to a different seller
 * (typically an AliExpress "Choice" cross-sell). The importer refuses to attribute it to the wrong
 * store; the dispatcher treats it as a quiet skip, not a failure.
 */
final class ForeignSellerException extends RuntimeException
{
}
