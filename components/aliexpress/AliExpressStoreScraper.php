<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use app\models\Setting;
use app\models\Store;
use RuntimeException;
use Throwable;
use Yii;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * Enumerates a store's product catalogue via the public `shoprenderview` component endpoint
 * (the same one the store front-end calls), paging until exhausted. Returns product stubs
 * for the importer to enrich via the Affiliate API.
 *
 * Verified live (2026-06): GET shoprenderview.aliexpress.com/async/execute?componentKey=allitems_choice
 * returns JSONP with result.products.{data,currentPage,totalPage,...}. Each item carries
 * id (=productId), subject, image*Url, promotionPiecePriceMoney, productExt.category_id_path, etc.
 *
 * Requires a valid AliExpress session Cookie (incl. x5sec) stored in Setting::ALIEXPRESS_COOKIE
 * — without it (or once x5sec expires) the endpoint serves an anti-bot "punish" page and we throw
 * a clear, actionable error telling the admin to refresh the cookie.
 */
final class AliExpressStoreScraper
{
    private const LISTING_URL = 'https://shoprenderview.aliexpress.com/async/execute';
    private const DEFAULT_DEVICE_ID = 'yOSuIsPH+kYCAW3zlA2S9zj4';

    public function __construct(
        private readonly Client $client = new Client(['transport' => 'yii\httpclient\CurlTransport']),
    ) {
    }

    /**
     * @return array<int, array{external_id:string, title:?string, image:?string}>
     */
    public function fetchProductStubs(Store $store, int $maxPages = 200, int $pageSize = 30): array
    {
        $sellerId = $this->resolveSellerId($store);
        $cookie = (string)Setting::get(Setting::ALIEXPRESS_COOKIE, '');
        if (trim($cookie) === '') {
            throw new RuntimeException('AliExpress session cookie is not set — paste it in admin (Hub → Session).');
        }
        $deviceId = $this->extractCookieValue($cookie, 'cna') ?? self::DEFAULT_DEVICE_ID;

        $stubs = [];
        $seen = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $decoded = $this->fetchPage($sellerId, $page, $pageSize, $deviceId, $cookie);
            $products = $decoded['result']['products'] ?? [];
            $items = is_array($products['data'] ?? null) ? $products['data'] : [];
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $externalId = $this->extractExternalId($item);
                if ($externalId === null || isset($seen[$externalId])) {
                    continue;
                }
                $seen[$externalId] = true;
                $stubs[] = [
                    'external_id' => $externalId,
                    'title'       => $this->extractTitle($item),
                    'image'       => $this->extractImage($item),
                ];
            }

            $totalPage = isset($products['totalPage']) ? (int)$products['totalPage'] : $page;
            if ($page >= $totalPage) {
                break;
            }
            sleep(random_int(3, 6)); // rate limit between pages
        }

        return $stubs;
    }

    /** Resolve & cache the store's sellerId (= ownerMemberId on the store page). */
    private function resolveSellerId(Store $store): string
    {
        if ($store->seller_id !== null && trim((string)$store->seller_id) !== '') {
            return (string)$store->seller_id;
        }

        $html = $this->fetchStorePageHtml($store->url);
        if (preg_match("~ownerMemberId\s*[:=]\s*['\"]?(\d{4,})~i", $html, $m) === 1) {
            $store->seller_id = $m[1];
            $store->save(false, ['seller_id']);

            return $m[1];
        }

        throw new RuntimeException('Could not resolve sellerId (ownerMemberId) from the store page.');
    }

    private function fetchPage(string $sellerId, int $page, int $pageSize, string $deviceId, string $cookie): array
    {
        $response = $this->client->createRequest()
            ->setMethod('GET')
            ->setUrl(self::LISTING_URL)
            ->setData([
                'componentKey' => 'allitems_choice',
                'deviceId'     => $deviceId,
                'SortType'     => 'bestmatch_sort',
                'page'         => $page,
                'pageSize'     => $pageSize,
                'country'      => 'PL',
                'site'         => 'pol',
                'sellerId'     => $sellerId,
                'groupId'      => '-1',
                'currency'     => 'PLN',
                'locale'       => 'pl_PL',
                'buyerId'      => '0',
                'callback'     => 'cb',
            ])
            ->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_ENCODING       => '', // accept gzip/br
            ])
            ->addHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0',
                'Accept'          => '*/*',
                'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                'Referer'         => 'https://pl.aliexpress.com/',
                'Sec-Fetch-Dest'  => 'script',
                'Sec-Fetch-Mode'  => 'no-cors',
                'Sec-Fetch-Site'  => 'same-site',
                'Cookie'          => $cookie,
            ])
            ->send();

        $body = (string)$response->getContent();
        if (str_contains($body, 'punishPath') || str_contains($body, 'x5secdata') || str_contains($body, '__baxia__')) {
            throw new RuntimeException('AliExpress x5sec cookie expired/blocked — refresh the session cookie in admin (Hub → Session).');
        }

        $decoded = $this->decodeJsonp($body);
        if (($decoded['code'] ?? null) !== 0 && ($decoded['success'] ?? null) !== true) {
            throw new RuntimeException('Store listing returned an error: ' . mb_substr(Json::encode($decoded['code'] ?? $decoded), 0, 200));
        }

        return $decoded;
    }

    private function fetchStorePageHtml(string $url): string
    {
        try {
            $response = $this->client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setOptions([
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 8,
                    CURLOPT_TIMEOUT        => 25,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_ENCODING       => '',
                ])
                ->addHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9',
                ])
                ->send();
        } catch (Throwable $e) {
            Yii::warning("Store page fetch failed for {$url}: {$e->getMessage()}", __METHOD__);

            return '';
        }

        return $response->isOk ? (string)$response->getContent() : '';
    }

    private function decodeJsonp(string $rawBody): array
    {
        $trimmed = trim($rawBody);
        if ($trimmed === '') {
            throw new RuntimeException('Empty store-listing response.');
        }
        if (preg_match('~^[A-Za-z_$][\w$]*\((.*)\)\s*;?\s*$~s', $trimmed, $m) !== 1) {
            throw new RuntimeException('Store-listing response is not JSONP.');
        }
        $decoded = Json::decode($m[1], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Decoded store-listing JSON is not an object.');
        }

        return $decoded;
    }

    private function extractExternalId(array $item): ?string
    {
        foreach (['id', 'productId', 'product_id', 'itemId'] as $key) {
            if (!isset($item[$key]) || !is_scalar($item[$key])) {
                continue;
            }
            $candidate = trim((string)$item[$key]);
            if (preg_match('~^\d{6,}$~', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractTitle(array $item): ?string
    {
        foreach (['subject', 'seoTitle', 'title', 'name'] as $key) {
            if (isset($item[$key]) && is_scalar($item[$key])) {
                $value = trim((string)$item[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractImage(array $item): ?string
    {
        foreach (['image640Url', 'skuImageUrl', 'image350Url', 'summImageUrl', 'scaleImageUrl', 'imageUrl'] as $key) {
            if (!isset($item[$key]) || !is_scalar($item[$key])) {
                continue;
            }
            $value = trim((string)$item[$key]);
            if ($value === '') {
                continue;
            }

            return str_starts_with($value, '//') ? 'https:' . $value : $value;
        }

        return null;
    }

    private function extractCookieValue(string $cookieHeader, string $name): ?string
    {
        $pattern = '~(?:^|;\s*)' . preg_quote($name, '~') . '=([^;]+)~';
        if (preg_match($pattern, $cookieHeader, $m) !== 1) {
            return null;
        }

        return trim($m[1]);
    }
}
