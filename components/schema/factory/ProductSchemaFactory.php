<?php

namespace app\components\schema\factory;

use app\models\Product;
use app\models\ProductReview;

final class ProductSchemaFactory
{
    public static function fromProduct(Product $product, string $productUrl, array $offers): array
    {
        $name = trim((string)$product->title) !== '' ? (string)$product->title : 'Product';

        $schema = [
            '@type'       => 'Product',
            '@id'         => '#product',
            'name'        => $name,
            'sku'         => (string)$product->external_id,
            'url'         => $productUrl,
            'description' => self::resolveDescription($product),
        ];

        $images = self::collectImageUrls($product);
        if ($images !== []) { $schema['image'] = $images; }
        if ($product->category !== null && trim((string)$product->category->name) !== '') {
            $schema['category'] = (string)$product->category->name;
        }

        $reviews = self::buildReviews($product);
        if ($reviews !== []) { $schema['review'] = count($reviews) === 1 ? $reviews[0] : $reviews; }

        $aggregate = self::buildAggregateRating($product);
        if ($aggregate !== null) { $schema['aggregateRating'] = $aggregate; }

        if ($offers !== []) { $schema['offers'] = count($offers) === 1 ? $offers[0] : $offers; }

        return $schema;
    }

    private static function resolveDescription(Product $product): string
    {
        $d = trim(strip_tags((string)$product->description));
        if ($d === '') { return (string)$product->title; }
        return mb_strlen($d) <= 220 ? $d : rtrim(mb_substr($d, 0, 220)) . '...';
    }

    private static function collectImageUrls(Product $product): array
    {
        $urls = [];
        foreach ($product->images as $image) {
            $u = trim((string)$image->url);
            if ($u !== '' && !in_array($u, $urls, true)) { $urls[] = $u; }
        }
        if ($urls === [] && trim((string)$product->main_image) !== '') { $urls[] = (string)$product->main_image; }
        return $urls;
    }

    private static function buildAggregateRating(Product $product): ?array
    {
        if ($product->rating_value === null || (float)$product->rating_value <= 0 || (int)$product->review_count < 1) {
            return null;
        }
        return [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format((float)$product->rating_value, 1, '.', ''),
            'reviewCount' => (int)$product->review_count,
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
    }

    private static function buildReviews(Product $product): array
    {
        $reviews = [];
        foreach ($product->reviews as $review) {
            if (!$review instanceof ProductReview) { continue; }
            $body = trim(strip_tags((string)$review->content));
            $hasRating = $review->rating_value !== null && (float)$review->rating_value > 0;
            if ($body === '' && !$hasRating) { continue; }

            $author = trim((string)$review->author_name);
            $node = ['@type' => 'Review', 'author' => ['@type' => $author !== '' ? 'Person' : 'Organization', 'name' => $author !== '' ? $author : 'Anonymous']];
            if ($body !== '') { $node['reviewBody'] = mb_strlen($body) <= 300 ? $body : rtrim(mb_substr($body, 0, 300)) . '...'; }
            if ($review->reviewed_at) { $node['datePublished'] = date(DATE_ATOM, (int)$review->reviewed_at); }
            if ($hasRating) {
                $scale = (float)$review->rating_scale_max > 0 ? (float)$review->rating_scale_max : 5.0;
                $node['reviewRating'] = ['@type' => 'Rating', 'ratingValue' => number_format(min(5.0, max(0.0, ((float)$review->rating_value / $scale) * 5.0)), 1, '.', ''), 'bestRating' => '5', 'worstRating' => '1'];
            }
            $reviews[] = $node;
            if (count($reviews) >= 5) { break; }
        }
        return $reviews;
    }
}
