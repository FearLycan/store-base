<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use RuntimeException;

/**
 * Thrown when the Affiliate API returns no record for a product id. This is not a transient error:
 * the item is either not in the affiliate program (non-commissionable), geo-restricted for the
 * affiliate account's country ("cannot be sold or promoted in the selected country"), or an invalid
 * id. None of these change on retry, so the product cannot be monetised through the affiliate link
 * and the importer gives up on it immediately rather than retrying five times.
 */
final class ProductNotAffiliableException extends RuntimeException implements NonRetryableJobException
{
}
