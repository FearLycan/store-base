<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use RuntimeException;
use Yii;
use yii\httpclient\Client;

/**
 * AliExpress Dropshipping API client (the "OP" gateway on api-sg.aliexpress.com).
 *
 * Unlike the Affiliate API (app_key + secret only), the DS API is OAuth-gated: a one-time
 * browser authorize grants a `code`, which is exchanged for an `access_token` (~10h) plus a
 * long-lived `refresh_token`, both persisted in {@see Setting}. {@see accessToken()} auto-refreshes
 * shortly before expiry.
 *
 * Signing/transport mirror the documented OP convention (verified against the public ae-api SDK):
 *   - sign_method=sha256, HMAC-SHA256(app_secret, signBase) uppercase hex
 *   - signBase = [apiPath]+ sorted(key.value) — the path prefix is used for the /rest/auth/* system
 *     calls and is empty for the /sync business gateway
 *   - timestamp is epoch milliseconds; requests are POST form-urlencoded
 *
 * {@see fetch()} returns the same shape as {@see AliExpressProductScraper::fetch()} so the importer
 * can swap one for the other (DS primary, x5sec scraper fallback).
 */
final class AliExpressDsClient
{
    private const API_BASE = 'https://api-sg.aliexpress.com';
    private const AUTHORIZE_URL = 'https://api-sg.aliexpress.com/oauth/authorize';
    private const PRODUCT_METHOD = 'aliexpress.ds.product.get';

    /** Refresh the access token when fewer than this many seconds remain before expiry. */
    private const REFRESH_SKEW = 600;

    /** Extra attempts made after the first when the API returns its per-second `ApiCallLimit` throttle. */
    private const RATE_LIMIT_RETRIES = 4;

    /**
     * Extra attempts made after the first when the API returns a transient server-side timeout
     * (`ServiceTimeout`/RPC timeout, `SystemBusy`, …) — the gateway's internal RPC was slow, not a
     * problem with our request, so a short backoff-and-retry usually succeeds.
     */
    private const TIMEOUT_RETRIES = 2;

    private Client $client;
    private DsTokenStore $tokens;

    public function __construct(?Client $client = null, ?DsTokenStore $tokens = null)
    {
        $this->client = $client ?? new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $this->tokens = $tokens ?? new DsTokenStore();
    }

    // --- OAuth -------------------------------------------------------------

    public function isConnected(): bool
    {
        return $this->tokens->isConnected();
    }

    /**
     * The AE consent page the admin visits; `redirect_uri` must match the one registered in the AE
     * console. `state` is echoed back to the callback for CSRF protection (the callback is public).
     */
    public function authorizeUrl(string $redirectUri, string $state = ''): string
    {
        $query = [
            'response_type' => 'code',
            'force_auth'    => 'true',
            'redirect_uri'  => $redirectUri,
            'client_id'     => $this->appKey(),
        ];
        if ($state !== '') {
            $query['state'] = $state;
        }

        return self::AUTHORIZE_URL . '?' . http_build_query($query);
    }

    /** Exchange the authorization `code` for tokens and persist them. */
    public function exchangeCode(string $code): void
    {
        $data = $this->request('/auth/token/create', ['code' => $code]);
        $this->persistTokens($data);
    }

    /** Force a refresh using the stored refresh_token; persists the new tokens. */
    public function refreshAccessToken(): void
    {
        $refresh = $this->tokens->refreshToken();
        if ($refresh === '') {
            throw new RuntimeException('No Dropshipping refresh_token stored — re-authorize the Dropshipping API.');
        }
        $data = $this->request('/auth/token/refresh', ['refresh_token' => $refresh]);
        $this->persistTokens($data);
    }

    /**
     * Refresh the shared token, but only if this instance wins the cross-deployment lock (or Redis is
     * off entirely). Returns whether we actually refreshed; false means a peer owns the refresh — or
     * Redis is down and we must not race it. Used by both the cron and the on-demand path below.
     */
    public function refreshTokenCoordinated(): bool
    {
        if (!$this->tokens->acquireRefreshLock()) {
            return false;
        }
        $this->refreshAccessToken();

        return true;
    }

    /** A valid access token, auto-refreshed (under the shared lock) when close to expiry. */
    public function accessToken(): string
    {
        $token = $this->tokens->accessToken();
        if ($token === '') {
            throw new RuntimeException('Dropshipping API not connected — authorize it in admin (Settings → Dropshipping API).');
        }

        $expiresAt = $this->tokens->expiresAt();
        if ($expiresAt > 0 && ($expiresAt - time()) < self::REFRESH_SKEW) {
            // Only the lock winner refreshes; everyone else re-reads, picking up a peer's fresh token if
            // there is one, otherwise coasting on the current one (still valid — we refresh 10 min early).
            $this->refreshTokenCoordinated();
            $token = $this->tokens->accessToken();
        }

        return $token;
    }

    // --- Product detail ----------------------------------------------------

    /**
     * Fetch the full detail bundle for a product via aliexpress.ds.product.get, normalized to the
     * scraper's contract so {@see ProductImporter} treats both sources identically.
     *
     * @return array{description:?string, images:array<int,string>, variants:array<int,array>, attributes:array<int,array{name:string,value:?string}>, base:array<string,mixed>}
     */
    public function fetch(string $productId): array
    {
        $response = $this->request('/sync', [
            'method'          => self::PRODUCT_METHOD,
            'access_token'    => $this->accessToken(),
            'product_id'      => $productId,
            'ship_to_country' => (string)(Yii::$app->params['aliexpress.shipToCountry'] ?? 'US'),
            'target_currency' => (string)(Yii::$app->params['aliexpress.targetCurrency'] ?? 'USD'),
            'target_language' => (string)(Yii::$app->params['aliexpress.targetLanguage'] ?? 'EN'),
        ]);

        $result = $response['aliexpress_ds_product_get_response']['result']
            ?? $response['result']
            ?? null;
        if (!is_array($result)) {
            throw new RuntimeException('ds.product.get returned no result for ' . $productId . '.');
        }

        $base = is_array($result['ae_item_base_info_dto'] ?? null) ? $result['ae_item_base_info_dto'] : [];
        $multimedia = is_array($result['ae_multimedia_info_dto'] ?? null) ? $result['ae_multimedia_info_dto'] : [];
        $images = $this->extractImages($multimedia);
        $variants = $this->extractVariants($result);

        return [
            'description' => $this->extractDescription($base),
            'images'      => $images,
            'variants'    => $variants,
            'attributes'  => $this->extractAttributes($result),
            // Core catalog fields (title/price/currency/rating/…), so the importer can build a product
            // straight from DS when the Affiliate API has no record of it (non-commissionable item).
            'base'        => $this->extractBase($base, $images, $variants),
        ];
    }

    /**
     * Affiliate-shaped core built from DS base info + cheapest variant, for the DS-only import path.
     * `price_cents` is the lowest variant sale price so the product's headline price matches its "from".
     *
     * @param array<int,array> $variants
     * @return array{title:?string, main_image:?string, currency_code:string, price_cents:?int, original_price_cents:?int, rating_value:?float, orders_count:int, category_id:?string}
     */
    private function extractBase(array $base, array $images, array $variants): array
    {
        $cheapest = $this->cheapestVariantPrice($variants);

        $rating = null;
        $rawRating = $base['avg_evaluation_rating'] ?? null;
        if (is_numeric($rawRating) && (float)$rawRating > 0) {
            $rating = round(min(5.0, (float)$rawRating), 2);
        }

        return [
            'title'                => $this->scalar($base, ['subject']),
            'main_image'           => $images[0] ?? null,
            // DS prices come back in the currency we requested above (aliexpress.targetCurrency).
            'currency_code'        => strtoupper((string)(Yii::$app->params['aliexpress.targetCurrency'] ?? 'USD')) ?: 'USD',
            'price_cents'          => $cheapest[0],
            'original_price_cents' => $cheapest[1],
            'rating_value'         => $rating,
            'orders_count'         => is_numeric($base['sales_count'] ?? null) ? (int)$base['sales_count'] : 0,
            'category_id'          => $this->scalar($base, ['category_id']),
        ];
    }

    /**
     * Lowest-priced variant as [sale_cents, original_cents] (prices already in minor units). Returns
     * [null, null] when no variant carries a parseable price.
     *
     * @param array<int,array> $variants
     * @return array{0:?int,1:?int}
     */
    private function cheapestVariantPrice(array $variants): array
    {
        $bestSale = null;
        $bestOriginal = null;
        foreach ($variants as $variant) {
            $sale = $variant['price'] ?? null;
            if (!is_int($sale)) {
                continue;
            }
            if ($bestSale === null || $sale < $bestSale) {
                $bestSale = $sale;
                $bestOriginal = is_int($variant['original_price'] ?? null) ? $variant['original_price'] : null;
            }
        }

        return [$bestSale, $bestOriginal];
    }

    private function extractDescription(array $base): ?string
    {
        foreach (['detail', 'mobile_detail'] as $key) {
            $value = $base[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return array<int,string> */
    private function extractImages(array $multimedia): array
    {
        $raw = $multimedia['image_urls'] ?? '';
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $urls = [];
        foreach (preg_split('~[;\s]+~', $raw) ?: [] as $entry) {
            $url = $this->normalizeUrl((string)$entry);
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /** @return array<int,array{name:string,value:?string}> */
    private function extractAttributes(array $result): array
    {
        $props = $this->extractList($result['ae_item_properties'] ?? null, 'ae_item_property');

        $attributes = [];
        foreach ($props as $prop) {
            if (!is_array($prop)) {
                continue;
            }
            $name = trim((string)($prop['attr_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $prop['attr_value'] ?? null;
            $attributes[] = ['name' => $name, 'value' => is_scalar($value) ? (string)$value : null];
        }

        return $attributes;
    }

    /** @return array<int,array> */
    private function extractVariants(array $result): array
    {
        $skus = $this->extractList($result['ae_item_sku_info_dtos'] ?? null, 'ae_item_sku_info_d_t_o');

        $variants = [];
        foreach ($skus as $sku) {
            if (!is_array($sku)) {
                continue;
            }

            $skuProps = $this->extractList(
                $sku['aeop_s_k_u_propertys'] ?? $sku['ae_sku_property_dtos'] ?? null,
                'ae_sku_property_d_t_o',
            );

            $nameParts = [];
            $options = [];
            $image = null;
            foreach ($skuProps as $prop) {
                if (!is_array($prop)) {
                    continue;
                }
                $propName = trim((string)($prop['sku_property_name'] ?? ''));
                $propValue = trim((string)($prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? ''));
                if ($propValue !== '') {
                    $nameParts[] = $propValue;
                    if ($propName !== '') {
                        $options[$propName] = $propValue;
                    }
                }
                if ($image === null && isset($prop['sku_image'])) {
                    $image = $this->normalizeUrl((string)$prop['sku_image']);
                }
            }

            $stock = $sku['sku_available_stock'] ?? $sku['s_k_u_available_stock'] ?? $sku['ipm_sku_stock'] ?? null;

            // `sku_attr` ("propId:valueId#label;…") is the identifier the DS order API needs; keep it
            // alongside the human-facing option map. Prefer the numeric `sku_id` as the stable key
            // (the `id`/`sku_code` fields echo the long sku_attr path and can blow the 64-char column).
            $skuAttr = $this->scalar($sku, ['sku_attr']);
            if ($skuAttr !== null) {
                $options['_sku_attr'] = $skuAttr;
            }

            $variants[] = [
                'external_sku_id' => $this->scalar($sku, ['sku_id', 'id', 'sku_code']),
                'name'            => $nameParts !== [] ? implode(' / ', $nameParts) : null,
                'options'         => $options !== [] ? $options : null,
                'price'           => $this->moneyToCents((string)($sku['offer_sale_price'] ?? $sku['sku_price'] ?? '')),
                'original_price'  => $this->moneyToCents((string)($sku['sku_price'] ?? '')),
                'stock'           => is_numeric($stock) ? (int)$stock : null,
                'image'           => $image,
            ];
        }

        return $variants;
    }

    // --- transport ---------------------------------------------------------

    /**
     * Signed POST to the OP gateway. `/auth/*` system methods go to the `/rest` host and sign with
     * the API path; the `/sync` business gateway signs without a path prefix.
     */
    private function request(string $pathname, array $params): array
    {
        $appSecret = $this->appSecret();
        $signPath = $pathname === '/sync' ? '' : $pathname;
        $url = self::API_BASE . (str_starts_with($pathname, '/auth') ? '/rest' : '') . $pathname;

        $rateLimitAttempts = 0;
        $timeoutAttempts = 0;
        while (true) {
            // Re-sign every attempt: the timestamp is part of the signature, so a retried request
            // needs a fresh timestamp + sign.
            $signed = array_merge($params, [
                'app_key'     => $this->appKey(),
                'sign_method' => 'sha256',
                'timestamp'   => (string)((int)round(microtime(true) * 1000)),
            ]);
            $signed = array_filter($signed, static fn ($v): bool => $v !== null && $v !== '');
            $signed['sign'] = $this->sign($signPath, $signed, $appSecret);

            $response = $this->client
                ->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->setFormat(Client::FORMAT_URLENCODED)
                ->addHeaders(['Accept' => 'application/json'])
                ->setData($signed)
                ->setOptions([CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10])
                ->send();

            if (!$response->isOk) {
                throw new RuntimeException('Dropshipping API HTTP error: ' . $response->statusCode . ' ' . $response->getContent());
            }

            $data = $response->getData();
            if (!is_array($data)) {
                throw new RuntimeException('Dropshipping API response is not valid JSON.');
            }

            $error = $data['error_response'] ?? null;
            if (is_array($error)) {
                $code = (string)($error['code'] ?? '');
                $message = (string)($error['msg'] ?? $error['message'] ?? '');
                if ($this->maybeRetry($code, $message, $rateLimitAttempts, $timeoutAttempts)) {
                    continue;
                }
                $detail = trim($code . ' ' . $message);
                throw new RuntimeException('Dropshipping API error: ' . ($detail !== '' ? $detail : 'unknown error'));
            }
            // OP gateway surfaces auth/system failures at the top level rather than under error_response.
            if (isset($data['code'], $data['request_id']) && (string)$data['code'] !== '0' && (string)$data['code'] !== '') {
                $code = (string)$data['code'];
                $message = (string)($data['message'] ?? $data['msg'] ?? '');
                if ($this->maybeRetry($code, $message, $rateLimitAttempts, $timeoutAttempts)) {
                    continue;
                }
                throw new RuntimeException('Dropshipping API error: ' . trim($code . ' ' . $message));
            }

            return $data;
        }
    }

    /**
     * Sleep-and-signal whether the caller should retry the request for a transient error. Rate-limit
     * throttles and server-side timeouts each get their own attempt budget and backoff curve; anything
     * else (bad params, auth, unknown) returns false so the caller throws immediately.
     */
    private function maybeRetry(string $code, string $message, int &$rateLimitAttempts, int &$timeoutAttempts): bool
    {
        if ($this->isRateLimitError($code, $message) && $rateLimitAttempts < self::RATE_LIMIT_RETRIES) {
            sleep($this->rateLimitBackoffSeconds($message, $rateLimitAttempts));
            $rateLimitAttempts++;

            return true;
        }
        if ($this->isTimeoutError($code, $message) && $timeoutAttempts < self::TIMEOUT_RETRIES) {
            sleep($this->timeoutBackoffSeconds($timeoutAttempts));
            $timeoutAttempts++;

            return true;
        }

        return false;
    }

    private function isRateLimitError(string $code, string $message): bool
    {
        return stripos($code, 'ApiCallLimit') !== false
            || stripos($message, 'access frequency exceeds the limit') !== false;
    }

    /**
     * A retryable server-side timeout / overload, as opposed to a client error. Covers the gateway's
     * `ServiceTimeout` (message "…failed due to RPC timeout"), plus the sibling `SystemBusy` /
     * `ServiceUnavailable` / remote-connection-timeout codes it returns under the same conditions.
     */
    private function isTimeoutError(string $code, string $message): bool
    {
        foreach (['ServiceTimeout', 'SystemBusy', 'ServiceUnavailable', 'remote-connection-timeout', 'remote-service-timeout'] as $needle) {
            if (stripos($code, $needle) !== false) {
                return true;
            }
        }

        return stripos($message, 'rpc timeout') !== false
            || stripos($message, 'system is busy') !== false;
    }

    /** Seconds to wait before retrying, derived from the ban stated in the message plus a growing safety margin. */
    private function rateLimitBackoffSeconds(string $message, int $attempt): int
    {
        $ban = preg_match('~ban will last\s+(\d+)\s*second~i', $message, $m) === 1 ? (int)$m[1] : 1;

        return min(10, max(1, $ban) + $attempt + 1);
    }

    /** Growing backoff for transient timeouts: give the overloaded backend a moment to recover (2s, 5s, …). */
    private function timeoutBackoffSeconds(int $attempt): int
    {
        return 2 + (3 * $attempt);
    }

    private function sign(string $signPath, array $params, string $appSecret): string
    {
        ksort($params);
        $base = $signPath;
        foreach ($params as $key => $value) {
            if ($key === 'sign') {
                continue;
            }
            $base .= $key . $value;
        }

        return strtoupper(hash_hmac('sha256', $base, $appSecret));
    }

    private function persistTokens(array $data): void
    {
        $access = trim((string)($data['access_token'] ?? ''));
        if ($access === '') {
            $message = trim((string)($data['code'] ?? '') . ' ' . (string)($data['message'] ?? $data['msg'] ?? ''));
            throw new RuntimeException('Token exchange returned no access_token' . ($message !== '' ? ' (' . $message . ')' : '') . '.');
        }

        $refresh = trim((string)($data['refresh_token'] ?? ''));

        // `expires_in` is seconds; fall back to the absolute `expire_time` (epoch ms) when absent.
        if (isset($data['expires_in']) && is_numeric($data['expires_in'])) {
            $expiresAt = time() + (int)$data['expires_in'];
        } elseif (isset($data['expire_time']) && is_numeric($data['expire_time'])) {
            $expiresAt = (int)round((int)$data['expire_time'] / 1000);
        } else {
            $expiresAt = time() + 3600;
        }

        // Redis is the shared source of truth; the token store also mirrors into this app's Setting table.
        $this->tokens->persist($access, $refresh !== '' ? $refresh : null, $expiresAt);
    }

    // --- helpers -----------------------------------------------------------

    /**
     * AE wraps repeated nodes inconsistently: a bare list, or `{ "<child>": [...] }`, or a single
     * object. Normalize all three to a plain list.
     *
     * @return array<int,mixed>
     */
    private function extractList(mixed $node, string $childKey): array
    {
        if (is_array($node) && isset($node[$childKey]) && is_array($node[$childKey])) {
            $node = $node[$childKey];
        }
        if (!is_array($node)) {
            return [];
        }
        if ($node === [] || array_is_list($node)) {
            return $node;
        }

        return [$node];
    }

    private function scalar(array $source, array $keys): ?string
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

    /** "$0.99" / "3.48" -> integer minor units (cents). Returns null when unparseable. */
    private function moneyToCents(string $raw): ?int
    {
        if ($raw === '' || preg_match('~(\d+(?:[.,]\d+)?)~', $raw, $m) !== 1) {
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

    private function appKey(): string
    {
        $key = trim((string)(Yii::$app->params['aliexpress.dropshipping.appKey'] ?? ''));
        if ($key === '') {
            throw new RuntimeException('aliexpress.dropshipping.appKey is not configured (set it in params-local.php).');
        }

        return $key;
    }

    private function appSecret(): string
    {
        $secret = trim((string)(Yii::$app->params['aliexpress.dropshipping.appSecret'] ?? ''));
        if ($secret === '') {
            throw new RuntimeException('aliexpress.dropshipping.appSecret is not configured (set it in params-local.php).');
        }

        return $secret;
    }
}
