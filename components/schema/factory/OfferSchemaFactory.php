<?php

namespace app\components\schema\factory;

use app\models\Product;

final class OfferSchemaFactory
{
    /** @return array<int,array> one offer (or empty when no price). */
    public static function fromProduct(Product $product, string $offerUrl): array
    {
        if ($product->price === null || $product->price <= 0) {
            return [];
        }
        $currency = strtoupper((string)$product->currency_code) ?: 'USD';

        $offer = [
            '@type'         => 'Offer',
            'price'         => number_format($product->price / 100, 2, '.', ''),
            'priceCurrency' => $currency,
            'availability'  => self::resolveAvailability($product->availability),
            'url'           => $offerUrl,
        ];
        if ($product->original_price !== null && $product->original_price > $product->price) {
            $offer['priceSpecification'] = [
                ['@type' => 'UnitPriceSpecification', 'name' => 'List price', 'price' => number_format($product->original_price / 100, 2, '.', ''), 'priceCurrency' => $currency],
                ['@type' => 'UnitPriceSpecification', 'name' => 'Sale price', 'price' => number_format($product->price / 100, 2, '.', ''), 'priceCurrency' => $currency],
            ];
        }

        return [$offer];
    }

    private static function resolveAvailability(?string $value): string
    {
        $n = strtolower(trim((string)$value));
        if ($n !== '' && (str_contains($n, 'out') || str_contains($n, 'sold'))) {
            return 'https://schema.org/OutOfStock';
        }
        return 'https://schema.org/InStock';
    }
}
