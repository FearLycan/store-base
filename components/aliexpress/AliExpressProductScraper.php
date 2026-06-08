<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use Throwable;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * Scrapes a product detail page for data the Affiliate API does not provide:
 * full image gallery, SKU/variants and specification attributes.
 *
 * NOTE (live-verification point — plan Task 15): AliExpress detail pages embed a
 * `window.runParams` JSON blob with imageModule / skuModule / specsModule / descriptionModule.
 * Those key paths shift periodically and differ across A/B layouts. The extractors below are
 * tolerant (return empty on miss) so the importer still succeeds on API core data when parsing
 * fails. Confirm key paths against a live page and adjust if gallery/variants/specs come back empty.
 */
final class AliExpressProductScraper
{
    public function __construct(
        private readonly Client $client = new Client(['transport' => 'yii\httpclient\CurlTransport']),
    ) {
    }

    /**
     * @return array{description:?string, images:array<int,string>, variants:array<int,array>, attributes:array<int,array{name:string,value:?string}>}
     */
    public function fetch(string $productId): array
    {
        $html = $this->fetchProductPageHtml($productId);
        $runParams = $html !== '' ? $this->extractRunParams($html) : [];

        return [
            'description' => $this->extractDescription($runParams),
            'images'      => $this->extractImages($runParams),
            'variants'    => $this->extractVariants($runParams),
            'attributes'  => $this->extractAttributes($runParams),
        ];
    }

    private function fetchProductPageHtml(string $productId): string
    {
        $response = $this->client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl('https://www.aliexpress.com/item/' . rawurlencode($productId) . '.html')
            ->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 8,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
            ])
            ->addHeaders([
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
                'Referer'         => 'https://www.aliexpress.com/',
            ])
            ->send();

        return $response->isOk ? (string)$response->getContent() : '';
    }

    private function extractRunParams(string $html): array
    {
        // window.runParams = {...};  (data may be under .data)
        if (preg_match('~window\.runParams\s*=\s*(\{.*?\});~s', $html, $matches) !== 1) {
            return [];
        }
        try {
            $decoded = Json::decode($matches[1], true);
        } catch (Throwable) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }

        return isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
    }

    private function extractImages(array $runParams): array
    {
        $paths = $runParams['imageModule']['imagePathList']
            ?? $runParams['imageModule']['imageList']
            ?? [];
        if (!is_array($paths)) {
            return [];
        }

        $urls = [];
        foreach ($paths as $entry) {
            $url = is_array($entry) ? ($entry['imageUrl'] ?? $entry['url'] ?? null) : $entry;
            if (!is_scalar($url)) {
                continue;
            }
            $normalized = $this->normalizeUrl((string)$url);
            if ($normalized !== null) {
                $urls[] = $normalized;
            }
        }

        return array_values(array_unique($urls));
    }

    private function extractVariants(array $runParams): array
    {
        $skuList = $runParams['skuModule']['skuPriceList'] ?? [];
        if (!is_array($skuList)) {
            return [];
        }

        $variants = [];
        foreach ($skuList as $sku) {
            if (!is_array($sku)) {
                continue;
            }
            $amount = $sku['skuVal']['skuAmount']['value']
                ?? $sku['skuVal']['skuActivityAmount']['value']
                ?? null;
            $original = $sku['skuVal']['skuCalPrice'] ?? $sku['skuVal']['skuAmount']['value'] ?? null;
            $variants[] = [
                'external_sku_id' => isset($sku['skuId']) ? (string)$sku['skuId'] : null,
                'name'            => isset($sku['skuAttr']) ? (string)$sku['skuAttr'] : null,
                'options'         => $this->extractVariantOptions($sku),
                'price'           => is_numeric($amount) ? (int)round((float)$amount * 100) : null,
                'original_price'  => is_numeric($original) ? (int)round((float)$original * 100) : null,
                'stock'           => isset($sku['skuVal']['availQuantity']) && is_numeric($sku['skuVal']['availQuantity'])
                    ? (int)$sku['skuVal']['availQuantity'] : null,
                'image'           => null,
            ];
        }

        return $variants;
    }

    private function extractVariantOptions(array $sku): ?array
    {
        // "skuAttr" looks like "14:200001460#Red;5:100014064#XL" — keep the raw mapping for the front-end.
        if (isset($sku['skuPropIds'])) {
            return ['skuPropIds' => (string)$sku['skuPropIds'], 'skuAttr' => isset($sku['skuAttr']) ? (string)$sku['skuAttr'] : null];
        }

        return null;
    }

    private function extractAttributes(array $runParams): array
    {
        $props = $runParams['specsModule']['props'] ?? [];
        if (!is_array($props)) {
            return [];
        }

        $attributes = [];
        foreach ($props as $prop) {
            if (!is_array($prop)) {
                continue;
            }
            $name = trim((string)($prop['attrName'] ?? $prop['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $prop['attrValue'] ?? $prop['value'] ?? null;
            $attributes[] = ['name' => $name, 'value' => is_scalar($value) ? (string)$value : null];
        }

        return $attributes;
    }

    private function extractDescription(array $runParams): ?string
    {
        // descriptionModule.descriptionUrl points to a secondary HTML fragment; fetch it best-effort.
        $descriptionUrl = $runParams['descriptionModule']['descriptionUrl'] ?? null;
        if (!is_string($descriptionUrl) || $descriptionUrl === '') {
            return null;
        }
        try {
            $response = $this->client->createRequest()
                ->setMethod('GET')
                ->setUrl($descriptionUrl)
                ->setOptions([CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8])
                ->addHeaders(['User-Agent' => 'Mozilla/5.0', 'Referer' => 'https://www.aliexpress.com/'])
                ->send();
        } catch (Throwable) {
            return null;
        }

        $body = trim((string)$response->getContent());

        return ($response->isOk && $body !== '') ? $body : null;
    }

    private function normalizeUrl(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }
        if (str_starts_with($normalized, '//')) {
            $normalized = 'https:' . $normalized;
        }
        if (!preg_match('~^https?://~i', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
