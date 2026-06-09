<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use RuntimeException;
use Throwable;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * Fetches product detail the Affiliate API does not provide — full HD image gallery, SKU/variants
 * and specification attributes — via the CSR data API `mtop.aliexpress.pdp.pc.query`.
 *
 * The item page is fully client-side rendered (empty window.runParams), so the data lives only in
 * this signed mtop endpoint. It is risk-controlled: it returns real data only when a valid `x5sec`
 * cookie is injected into the session (see AliExpressMtopSession::injectStoredRiskCookies, fed from
 * the admin-pasted Setting). Without it the call returns RGV587 and we throw — the importer then
 * falls back to the API core (main image only).
 */
final class AliExpressProductScraper
{
    private const PDP_API = 'mtop.aliexpress.pdp.pc.query';

    public function __construct(
        private readonly AliExpressMtopSession $session = new AliExpressMtopSession(),
        private readonly Client $client = new Client(['transport' => 'yii\httpclient\CurlTransport']),
    ) {
    }

    /**
     * @return array{description:?string, images:array<int,string>, variants:array<int,array>, attributes:array<int,array{name:string,value:?string}>}
     */
    public function fetch(string $productId): array
    {
        $this->session->bootstrapForProduct($productId);
        $decoded = $this->session->call(self::PDP_API, [
            'productId'  => $productId,
            '_lang'      => 'en_US',
            '_currency'  => 'USD',
            'country'    => 'US',
            'province'   => '',
            'city'       => '',
            'channel'    => '',
            'pdp_ext_f'  => '',
            'sourceType' => '',
            'clientType' => 'pc',
        ]);

        $ret = (string)($decoded['ret'][0] ?? '');
        if (!str_contains($ret, 'SUCCESS')) {
            throw new RuntimeException('pdp.pc.query failed (' . $ret . ') — refresh the x5sec cookie in admin (Hub → Session).');
        }

        $result = $decoded['data']['result'] ?? [];
        if (!is_array($result)) {
            $result = [];
        }

        return [
            'description' => $this->extractDescription($result),
            'images'      => $this->extractImages($result),
            'variants'    => $this->extractVariants($result),
            'attributes'  => $this->extractAttributes($result),
        ];
    }

    /** @return array<int,string> */
    private function extractImages(array $result): array
    {
        $module = $result['HEADER_IMAGE_PC'] ?? [];
        $urls = [];

        foreach (($module['mainImages'] ?? []) as $entry) {
            if (is_array($entry) && isset($entry['imageUrl']) && is_scalar($entry['imageUrl'])) {
                $urls[] = (string)$entry['imageUrl'];
            }
        }
        if ($urls === []) {
            foreach (($module['imagePathList'] ?? []) as $entry) {
                if (is_scalar($entry)) {
                    $urls[] = (string)$entry;
                }
            }
        }

        $normalized = [];
        foreach ($urls as $url) {
            $value = $this->normalizeUrl($url);
            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** @return array<int,array{name:string,value:?string}> */
    private function extractAttributes(array $result): array
    {
        $props = $result['PRODUCT_PROP_PC']['showedProps'] ?? $result['PRODUCT_PROP_PC']['outerProps'] ?? [];
        if (!is_array($props)) {
            return [];
        }

        $attributes = [];
        foreach ($props as $prop) {
            if (!is_array($prop)) {
                continue;
            }
            $name = trim((string)($prop['attrName'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $prop['attrValue'] ?? null;
            $attributes[] = ['name' => $name, 'value' => is_scalar($value) ? (string)$value : null];
        }

        return $attributes;
    }

    /** @return array<int,array> */
    private function extractVariants(array $result): array
    {
        $skuPaths = $result['SKU']['skuPaths'] ?? [];
        if (!is_array($skuPaths) || $skuPaths === []) {
            return [];
        }
        $priceMap = $result['PRICE']['skuIdStrPriceInfoMap'] ?? [];
        $priceMap = is_array($priceMap) ? $priceMap : [];
        $skuImagesMap = $result['HEADER_IMAGE_PC']['skuImagesMap'] ?? [];
        $skuImagesMap = is_array($skuImagesMap) ? $skuImagesMap : [];

        $variants = [];
        foreach ($skuPaths as $sku) {
            if (!is_array($sku)) {
                continue;
            }
            $skuId = isset($sku['skuId']) ? (string)$sku['skuId'] : (isset($sku['skuIdStr']) ? (string)$sku['skuIdStr'] : '');
            if ($skuId === '') {
                continue;
            }
            $skuAttr = isset($sku['skuAttr']) ? (string)$sku['skuAttr'] : null;
            $priceInfo = is_array($priceMap[$skuId] ?? null) ? $priceMap[$skuId] : [];

            $variants[] = [
                'external_sku_id' => $skuId,
                'name'            => $this->skuLabel($skuAttr),
                'options'         => ['skuAttr' => $skuAttr, 'path' => isset($sku['path']) ? (string)$sku['path'] : null],
                'price'           => $this->moneyToCents((string)($priceInfo['salePriceString'] ?? '')),
                'original_price'  => $this->moneyToCents((string)($priceInfo['originalPrice']['value'] ?? '')),
                'stock'           => isset($sku['skuStock']) && is_numeric($sku['skuStock']) ? (int)$sku['skuStock'] : null,
                'image'           => $this->skuImage($skuImagesMap[$skuId] ?? null),
            ];
        }

        return $variants;
    }

    private function extractDescription(array $result): ?string
    {
        $url = $result['DESC']['pcDescUrl'] ?? $result['DESC']['nativeDescUrl'] ?? null;
        if (!is_string($url) || $url === '') {
            return null;
        }
        try {
            $response = $this->client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setOptions([CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_ENCODING => ''])
                ->addHeaders(['User-Agent' => 'Mozilla/5.0', 'Referer' => 'https://www.aliexpress.com/'])
                ->send();
        } catch (Throwable) {
            return null;
        }
        $body = trim((string)$response->getContent());

        return ($response->isOk && $body !== '') ? $body : null;
    }

    /** "14:173#A3" -> "A3" (the human-facing option label). */
    private function skuLabel(?string $skuAttr): ?string
    {
        if ($skuAttr === null || $skuAttr === '') {
            return null;
        }
        $hash = strpos($skuAttr, '#');

        return $hash !== false ? substr($skuAttr, $hash + 1) : $skuAttr;
    }

    private function skuImage(mixed $entry): ?string
    {
        if (is_string($entry)) {
            return $this->normalizeUrl($entry);
        }
        if (is_array($entry)) {
            foreach (['imageUrl', 'url', 'image'] as $key) {
                if (isset($entry[$key]) && is_scalar($entry[$key])) {
                    return $this->normalizeUrl((string)$entry[$key]);
                }
            }
        }

        return null;
    }

    /** "$0.99" / "3.48" -> integer minor units (cents). Returns null when unparseable. */
    private function moneyToCents(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }
        if (preg_match('~(\d+(?:[.,]\d+)?)~', $raw, $m) !== 1) {
            return null;
        }

        return (int)round((float)str_replace(',', '.', $m[1]) * 100);
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
