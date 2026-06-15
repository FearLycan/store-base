<?php

namespace app\components\schema\builder;

use app\components\schema\factory\BreadcrumbListSchemaFactory;
use app\components\schema\factory\OfferSchemaFactory;
use app\components\schema\factory\ProductSchemaFactory;
use app\models\Product;

final class ProductPageSchemaBuilder
{
    /**
     * @param array<int,array{label:string,url:?string}> $links breadcrumb links (excluding home + current)
     * @param array{label:string,url:?string} $homeLink
     */
    public static function build(Product $product, string $productUrl, string $offerUrl, array $links, array $homeLink): array
    {
        $offers = OfferSchemaFactory::fromProduct($product, $offerUrl);
        return [
            ProductSchemaFactory::fromProduct($product, $productUrl, $offers),
            BreadcrumbListSchemaFactory::fromView($links, $homeLink, $product->displayName),
        ];
    }
}
