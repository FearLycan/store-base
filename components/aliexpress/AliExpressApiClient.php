<?php

namespace app\components\aliexpress;

use RuntimeException;
use Yii;
use yii\helpers\Json;
use yii\httpclient\Client;

final class AliExpressApiClient
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $baseUrl = (string)(Yii::$app->params['aliexpress.apiBaseUrl'] ?? 'https://api-sg.aliexpress.com/sync');
        $this->client = $client ?? new Client([
            'baseUrl'   => rtrim($baseUrl, '/'),
            'transport' => 'yii\httpclient\CurlTransport',
        ]);
    }

    public function fetchProductByItemId(string $itemId): array
    {
        $requestParams = [
            'product_ids'     => $itemId,
            'target_currency' => (string)(Yii::$app->params['aliexpress.targetCurrency'] ?? 'USD'),
            'target_language' => (string)(Yii::$app->params['aliexpress.targetLanguage'] ?? 'EN'),
            'ship_to_country' => (string)(Yii::$app->params['aliexpress.shipToCountry'] ?? 'US'),
        ];

        $trackingId = trim((string)(Yii::$app->params['aliexpress.trackingId'] ?? ''));
        if ($trackingId !== '') {
            $requestParams['tracking_id'] = $trackingId;
        }

        $data = $this->sendSignedRequest('aliexpress.affiliate.productdetail.get', $requestParams);

        $products = $this->extractProducts($data);
        if ($products === []) {
            throw new RuntimeException('No products found in AliExpress API response.');
        }

        foreach ($products as $product) {
            if ($this->extractString($product, ['product_id', 'item_id', 'productId']) === $itemId) {
                return $this->normalizeProduct($product, $trackingId);
            }
        }

        return $this->normalizeProduct($products[0], $trackingId);
    }

    public function fetchProductsByKeywords(string $keywords, int $page = 1, int $pageSize = 20): array
    {
        $normalizedKeywords = trim($keywords);
        if ($normalizedKeywords === '') {
            return [];
        }

        $normalizedPage = max(1, $page);
        $normalizedPageSize = min(50, max(1, $pageSize));
        $requestParams = [
            'keywords'        => $normalizedKeywords,
            'page_no'         => $normalizedPage,
            'page_size'       => $normalizedPageSize,
            'target_currency' => (string)(Yii::$app->params['aliexpress.targetCurrency'] ?? 'USD'),
            'target_language' => (string)(Yii::$app->params['aliexpress.targetLanguage'] ?? 'EN'),
            'ship_to_country' => (string)(Yii::$app->params['aliexpress.shipToCountry'] ?? 'US'),
        ];

        $data = $this->sendSignedRequest('aliexpress.affiliate.product.query', $requestParams);
        $products = $this->extractProducts($data);
        if ($products === []) {
            return [];
        }

        $items = [];
        foreach ($products as $product) {
            $items[] = $this->normalizeProductForDiscovery($product);
        }

        return $items;
    }

    private function sendSignedRequest(string $method, array $extraParams): array
    {
        $appKey = trim((string)(Yii::$app->params['aliexpress.appKey'] ?? ''));
        $appSecret = trim((string)(Yii::$app->params['aliexpress.appSecret'] ?? ''));
        if ($appKey === '' || $appSecret === '') {
            throw new RuntimeException('AliExpress API credentials are missing. Configure aliexpress.appKey and aliexpress.appSecret.');
        }

        $requestParams = array_merge([
            'method'      => $method,
            'app_key'     => $appKey,
            'sign_method' => 'md5',
            'timestamp'   => gmdate('Y-m-d H:i:s'),
            'format'      => 'json',
            'v'           => '2.0',
        ], $extraParams);
        $requestParams['sign'] = $this->generateSign($requestParams, $appSecret);

        $response = $this->client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl('')
            ->setData($requestParams)
            ->send();

        if (!$response->isOk) {
            throw new RuntimeException('AliExpress API request failed: ' . $response->getContent());
        }

        $data = $response->getData();
        if (!is_array($data)) {
            throw new RuntimeException('AliExpress API response is not valid JSON.');
        }

        $error = $data['error_response'] ?? null;
        if (is_array($error)) {
            $code = (string)($error['code'] ?? '');
            $message = (string)($error['msg'] ?? $error['sub_msg'] ?? 'Unknown API error');
            $details = trim($code . ' ' . $message);
            throw new RuntimeException('AliExpress API error: ' . ($details !== '' ? $details : 'unknown error'));
        }

        return $data;
    }

    private function generateSign(array $params, string $appSecret): string
    {
        ksort($params);
        $payload = $appSecret;
        foreach ($params as $key => $value) {
            if ($key === 'sign' || $value === null || $value === '') {
                continue;
            }

            $payload .= $key . $value;
        }
        $payload .= $appSecret;

        return strtoupper(md5($payload));
    }

    private function extractProducts(array $data): array
    {
        $response = $data['aliexpress_affiliate_productdetail_get_response']
            ?? $data['aliexpress_affiliate_product_query_response']
            ?? $data;

        if (!is_array($response) && count($data) === 1) {
            $firstValue = reset($data);
            if (is_array($firstValue)) {
                $response = $firstValue;
            }
        }

        if (!is_array($response)) {
            return [];
        }

        $result = $response['resp_result']['result'] ?? $response['result'] ?? null;
        if (is_string($result) && trim($result) !== '') {
            try {
                $decoded = Json::decode($result, true);
                if (is_array($decoded)) {
                    $result = $decoded;
                }
            } catch (\Throwable) {
                return [];
            }
        }

        if (!is_array($result)) {
            return [];
        }

        $container = $result['products'] ?? $result['result'] ?? $result;
        if (!is_array($container)) {
            return [];
        }

        $products = $container['product'] ?? $container['products'] ?? $container;

        if (!is_array($products)) {
            return [];
        }

        if (isset($products['product_id']) || isset($products['item_id'])) {
            return [$products];
        }

        $list = [];
        foreach ($products as $entry) {
            if (is_array($entry)) {
                $list[] = $entry;
            }
        }

        return $list;
    }

    private function normalizeProduct(array $product, string $trackingId): array
    {
        $title = $this->extractString($product, ['product_title', 'title', 'product_name']);
        $productUrl = $this->extractString($product, ['product_detail_url', 'product_url', 'promotion_link', 'promotion_link_url']);
        $imageUrl = $this->extractString($product, ['product_main_image_url', 'main_image_url', 'image_url']);
        $currency = strtoupper($this->extractString($product, ['target_sale_price_currency', 'sale_price_currency', 'currency_code', 'currency']) ?? 'USD');
        $priceAmount = $this->extractPriceValue($product);
        $reviewCount = $this->extractInt($product, ['lastest_volume', 'orders_count', 'review_count']);
        $ratingValue = $this->extractFloat($product, ['evaluate_rate', 'rating_value']);
        $affiliateUrl = $this->generateAffiliateLink($productUrl, $trackingId);

        return [
            'external_id'      => $this->extractString($product, ['product_id', 'item_id', 'productId']),
            'name'             => $title,
            'url'              => $affiliateUrl,
            'image'            => $imageUrl,
            'currency_code'    => $currency !== '' ? $currency : 'USD',
            'price_cents'      => $priceAmount !== null ? (int)round($priceAmount * 100) : null,
            'availability'     => $this->extractString($product, ['availability', 'stock_status']),
            'rating_value'     => $ratingValue,
            'rating_scale_max' => $ratingValue !== null ? 5.0 : null,
            'review_count'     => $reviewCount ?? 0,
        ];
    }

    private function normalizeProductForDiscovery(array $product): array
    {
        $title = $this->extractString($product, ['product_title', 'title', 'product_name']);
        $productUrl = $this->extractString($product, ['product_detail_url', 'product_url', 'promotion_link', 'promotion_link_url']);
        $imageUrl = $this->extractString($product, ['product_main_image_url', 'main_image_url', 'image_url']);
        $currency = strtoupper($this->extractString($product, ['target_sale_price_currency', 'sale_price_currency', 'currency_code', 'currency']) ?? 'USD');
        $priceAmount = $this->extractPriceValue($product);
        $reviewCount = $this->extractInt($product, ['lastest_volume', 'orders_count', 'review_count']);
        $ratingValue = $this->extractFloat($product, ['evaluate_rate', 'rating_value']);

        return [
            'external_id'      => $this->extractString($product, ['product_id', 'item_id', 'productId']),
            'name'             => $title,
            'url'              => $productUrl,
            'image'            => $imageUrl,
            'currency_code'    => $currency !== '' ? $currency : 'USD',
            'price_cents'      => $priceAmount !== null ? (int)round($priceAmount * 100) : null,
            'availability'     => $this->extractString($product, ['availability', 'stock_status']),
            'rating_value'     => $ratingValue,
            'rating_scale_max' => $ratingValue !== null ? 5.0 : null,
            'review_count'     => $reviewCount ?? 0,
        ];
    }

    private function generateAffiliateLink(?string $productUrl, string $trackingId): string
    {
        if ($productUrl === null || $productUrl === '') {
            throw new RuntimeException('AliExpress API did not return product URL required for affiliate link generation.');
        }

        if ($trackingId === '') {
            throw new RuntimeException('aliexpress.trackingId is required to generate affiliate links.');
        }

        $response = $this->sendSignedRequest('aliexpress.affiliate.link.generate', [
            'promotion_link_type' => 0,
            'source_values'       => $productUrl,
            'tracking_id'         => $trackingId,
        ]);

        $generated = $this->extractGeneratedAffiliateLink($response);
        if ($generated === null || $generated === '') {
            throw new RuntimeException('Failed to generate affiliate link from AliExpress API response.');
        }

        return $generated;
    }

    private function extractGeneratedAffiliateLink(array $data): ?string
    {
        $response = $data['aliexpress_affiliate_link_generate_response'] ?? $data;
        if (!is_array($response)) {
            return null;
        }

        $result = $response['resp_result']['result'] ?? $response['result'] ?? null;
        if (is_string($result) && trim($result) !== '') {
            try {
                $decoded = Json::decode($result, true);
                if (is_array($decoded)) {
                    $result = $decoded;
                }
            } catch (\Throwable) {
                return null;
            }
        }

        if (!is_array($result)) {
            return null;
        }

        $links = $result['promotion_links']['promotion_link'] ?? $result['promotion_link'] ?? $result['links'] ?? null;
        if (is_array($links) && isset($links[0]) && is_array($links[0])) {
            $first = $links[0];

            return $this->extractString($first, ['promotion_link', 'promotion_link_url']);
        }

        if (is_array($links)) {
            return $this->extractString($links, ['promotion_link', 'promotion_link_url']);
        }

        return $this->extractString($result, ['promotion_link', 'promotion_link_url']);
    }

    private function extractString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (is_scalar($value)) {
                $normalized = trim((string)$value);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function extractPriceValue(array $source): ?float
    {
        foreach (['target_sale_price', 'sale_price', 'app_sale_price', 'original_price'] as $key) {
            $value = $source[$key] ?? null;
            if (is_numeric($value)) {
                return (float)$value;
            }

            if (is_string($value)) {
                if (preg_match('~(-?\d+(?:[.,]\d+)?)~', $value, $matches) === 1) {
                    return (float)str_replace(',', '.', $matches[1]);
                }
            }
        }

        return null;
    }

    private function extractInt(array $source, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (is_numeric($value)) {
                return (int)$value;
            }
        }

        return null;
    }

    private function extractFloat(array $source, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (is_numeric($value)) {
                return (float)$value;
            }
        }

        return null;
    }
}
