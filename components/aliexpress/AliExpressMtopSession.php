<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use RuntimeException;
use Yii;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * Shared mtop (acs.aliexpress.com h5 gateway) session: cookie bootstrap, _m_h5_tk token
 * acquisition, request signing, JSONP decoding and sellerAdminSeq resolution.
 *
 * Used by the store, product and review scrapers. The _m_h5_tk token is shared per session,
 * so any of the bootstrap* methods makes call() usable afterwards.
 */
final class AliExpressMtopSession
{
    private const GATEWAY = 'https://acs.aliexpress.com/h5/';
    private const TOKEN_SEED_API = 'mtop.aliexpress.review.pc.list';

    private Client $client;
    private string $appKey;
    private string $lang;
    private string $country;
    private string $cookieHeader = '';
    private string $token = '';

    public function __construct(?Client $client = null)
    {
        $this->client  = $client ?? new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $this->appKey  = (string)(Yii::$app->params['aliexpress.mtop.appKey'] ?? '12574478');
        $this->lang    = (string)(Yii::$app->params['aliexpress.mtop.lang'] ?? 'en_US');
        $this->country = (string)(Yii::$app->params['aliexpress.mtop.country'] ?? 'US');
    }

    public function getCookieHeader(): string
    {
        return $this->cookieHeader;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    /** Bootstrap cookies + token using a product page. */
    public function bootstrapForProduct(string $productId): void
    {
        $url = 'https://www.aliexpress.com/item/' . rawurlencode($productId) . '.html';
        [$html, $cookieHeader] = $this->fetchPageCookies($url);
        $this->cookieHeader = $cookieHeader;
        $this->acquireToken($productId);
    }

    /** Bootstrap cookies using a store page (for store listing calls). */
    public function bootstrapForStore(string $storeUrl): void
    {
        [$html, $cookieHeader] = $this->fetchPageCookies($storeUrl);
        $this->cookieHeader = $cookieHeader;
        // Any productId works for the token handshake; token is shared per session.
        $this->acquireToken('1');
    }

    /**
     * Perform a signed mtop GET; returns the decoded JSON array.
     *
     * @throws RuntimeException on transport or JSONP errors, or when no token is available.
     */
    public function call(string $api, array $data): array
    {
        if ($this->token === '') {
            throw new RuntimeException('mtop session not bootstrapped: missing _m_h5_tk token.');
        }

        $timestamp = (string)((int)floor(microtime(true) * 1000));
        $payload   = Json::encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sign      = md5($this->token . '&' . $timestamp . '&' . $this->appKey . '&' . $payload);

        $query = [
            'jsv'      => '2.5.1',
            'appKey'   => $this->appKey,
            't'        => $timestamp,
            'sign'     => $sign,
            'api'      => $api,
            'v'        => '1.0',
            'type'     => 'jsonp',
            'dataType' => 'jsonp',
            'callback' => 'mtopjsonp_call',
            'data'     => $payload,
        ];

        $headers = $this->defaultHeaders();
        if ($this->cookieHeader !== '') {
            $headers['Cookie'] = $this->cookieHeader;
        }

        $response = $this->client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl(self::GATEWAY . $api . '/1.0/')
            ->addHeaders($headers)
            ->setData($query)
            ->send();

        // mtop may refresh the token cookie; keep it current.
        $this->mergeResponseCookies($response);

        return $this->decodeJsonp((string)$response->getContent());
    }

    /**
     * Resolve sellerAdminSeq for a product (needed by the review endpoint). Returns '' if not found.
     */
    public function resolveSellerAdminSeq(string $productId): string
    {
        $html = $this->fetchProductPageHtml($productId);
        if ($html !== '') {
            $detected = $this->detectSellerAdminSeqFromHtml($html);
            if ($detected !== '') {
                return $detected;
            }
            $feedbackHtml = $this->fetchFeedbackPageHtml($html);
            if ($feedbackHtml !== '') {
                $detected = $this->detectSellerAdminSeqFromHtml($feedbackHtml);
                if ($detected !== '') {
                    return $detected;
                }
            }
        }

        if ($this->token !== '') {
            return $this->detectSellerAdminSeqFromMtopProbe($productId);
        }

        return '';
    }

    // --- session internals -------------------------------------------------

    private function defaultHeaders(): array
    {
        return [
            'Accept'          => '*/*',
            'Accept-Language' => 'en-US,en;q=0.9',
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            'Referer'         => 'https://www.aliexpress.com/',
        ];
    }

    private function htmlHeaders(): array
    {
        return [
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            'Referer'         => 'https://www.aliexpress.com/',
        ];
    }

    /** GET a page, returning [html, cookieHeader] built from its Set-Cookie headers. */
    private function fetchPageCookies(string $url): array
    {
        $response = $this->client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl($url)
            ->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 8,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
            ])
            ->addHeaders($this->htmlHeaders())
            ->send();

        $html = (string)$response->getContent();
        $cookieHeader = $this->buildCookieHeaderFromMap($this->parseSetCookies($this->setCookieList($response)));

        return [$html, $cookieHeader];
    }

    /**
     * mtop handshake: the first signed call returns FAIL_SYS_TOKEN_* plus a fresh _m_h5_tk cookie;
     * we merge it so subsequent call()s are authenticated.
     */
    private function acquireToken(string $productId): void
    {
        $timestamp = (string)((int)floor(microtime(true) * 1000));
        $data = Json::encode([
            'productId'      => $productId,
            'page'           => 1,
            'pageSize'       => 1,
            '_lang'          => $this->lang,
            'filter'         => 'all',
            'sort'           => 'complex_default',
            'country'        => $this->country,
            'sellerAdminSeq' => '0',
            'clientType'     => 'web',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = $this->defaultHeaders();
        if ($this->cookieHeader !== '') {
            $headers['Cookie'] = $this->cookieHeader;
        }

        $response = $this->client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl(self::GATEWAY . self::TOKEN_SEED_API . '/1.0/')
            ->addHeaders($headers)
            ->setData([
                'jsv'      => '2.5.1',
                'appKey'   => $this->appKey,
                't'        => $timestamp,
                'sign'     => md5('bootstrap-' . $timestamp),
                'api'      => self::TOKEN_SEED_API,
                'v'        => '1.0',
                'type'     => 'jsonp',
                'dataType' => 'jsonp',
                'callback' => 'mtopjsonp_bootstrap',
                'data'     => $data,
            ])
            ->send();

        $this->mergeResponseCookies($response);
        $this->token = $this->resolveToken($this->cookieHeader);
    }

    private function mergeResponseCookies($response): void
    {
        $extra = $this->buildCookieHeaderFromMap($this->parseSetCookies($this->setCookieList($response)));
        if ($extra === '') {
            return;
        }
        $this->cookieHeader = $this->mergeCookieHeaders($this->cookieHeader, $extra);
        $token = $this->resolveToken($this->cookieHeader);
        if ($token !== '') {
            $this->token = $token;
        }
    }

    private function setCookieList($response): array
    {
        $setCookies = $response->headers->get('set-cookie', null, false);

        return is_array($setCookies) ? $setCookies : [];
    }

    private function resolveToken(string $cookieHeader): string
    {
        $cookieToken = $this->extractCookieValue($cookieHeader, '_m_h5_tk');

        return $cookieToken !== null ? $this->extractTokenPrefix($cookieToken) : '';
    }

    private function extractTokenPrefix(string $tokenValue): string
    {
        $separatorPos = strpos($tokenValue, '_');

        return $separatorPos === false ? $tokenValue : substr($tokenValue, 0, $separatorPos);
    }

    private function extractCookieValue(string $cookieHeader, string $name): ?string
    {
        $pattern = '~(?:^|;\s*)' . preg_quote($name, '~') . '=([^;]+)~';
        if (preg_match($pattern, $cookieHeader, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function parseSetCookies(array $setCookieHeaders): array
    {
        $cookieMap = [];
        foreach ($setCookieHeaders as $setCookieHeader) {
            if (!is_string($setCookieHeader)) {
                continue;
            }
            $firstPart = trim((string)explode(';', $setCookieHeader, 2)[0]);
            if ($firstPart === '' || strpos($firstPart, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $firstPart, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '' || $value === '') {
                continue;
            }
            $cookieMap[$name] = $value;
        }

        return $cookieMap;
    }

    private function buildCookieHeaderFromMap(array $cookieMap): string
    {
        $pairs = [];
        foreach ($cookieMap as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return implode('; ', $pairs);
    }

    private function mergeCookieHeaders(string $baseCookieHeader, string $extraCookieHeader): string
    {
        $cookieMap = [];
        foreach ([$baseCookieHeader, $extraCookieHeader] as $header) {
            $parts = array_filter(array_map('trim', explode(';', $header)));
            foreach ($parts as $part) {
                if (strpos($part, '=') === false) {
                    continue;
                }
                [$name, $value] = explode('=', $part, 2);
                $name = trim($name);
                $value = trim($value);
                if ($name === '' || $value === '') {
                    continue;
                }
                $cookieMap[$name] = $value;
            }
        }

        return $this->buildCookieHeaderFromMap($cookieMap);
    }

    private function decodeJsonp(string $rawBody): array
    {
        $trimmed = trim($rawBody);
        if ($trimmed === '') {
            throw new RuntimeException('Empty mtop response body.');
        }
        if (preg_match('~^[^(]+\((.*)\)\s*;?\s*$~s', $trimmed, $matches) !== 1) {
            throw new RuntimeException('mtop response is not JSONP.');
        }
        $decoded = Json::decode($matches[1], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Decoded mtop JSON is not an object/array.');
        }

        return $decoded;
    }

    // --- sellerAdminSeq detection -----------------------------------------

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
            ->addHeaders($this->cookieHeader !== '' ? $this->htmlHeaders() + ['Cookie' => $this->cookieHeader] : $this->htmlHeaders())
            ->send();

        return $response->isOk ? (string)$response->getContent() : '';
    }

    private function fetchFeedbackPageHtml(string $productPageHtml): string
    {
        $feedbackUrl = $this->extractFeedbackUrlFromProductHtml($productPageHtml);
        if ($feedbackUrl === '') {
            return '';
        }
        $response = $this->client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl($feedbackUrl)
            ->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 8,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
            ])
            ->addHeaders($this->cookieHeader !== '' ? $this->htmlHeaders() + ['Cookie' => $this->cookieHeader] : $this->htmlHeaders())
            ->send();

        return $response->isOk ? (string)$response->getContent() : '';
    }

    private function extractFeedbackUrlFromProductHtml(string $html): string
    {
        if (preg_match('~https?://[^"\']+/page/feedback[^"\']+~i', $html, $matches) !== 1) {
            return '';
        }
        $url = html_entity_decode(trim($matches[0]), ENT_QUOTES | ENT_HTML5);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }

    private function sellerAdminSeqPatterns(): array
    {
        return [
            '~"sellerAdminSeq"\s*:\s*"(?P<value>\d+)"~',
            '~"sellerAdminSeq"\s*:\s*(?P<value>\d+)~',
            "~'sellerAdminSeq'\s*:\s*'(?P<value>\d+)'~",
            "~'sellerAdminSeq'\s*:\s*(?P<value>\d+)~",
            '~"ownerMemberId"\s*:\s*"(?P<value>\d+)"~',
            '~"ownerMemberId"\s*:\s*(?P<value>\d+)~',
            '~sellerAdminSeq=(?P<value>\d+)~',
            '~sellerAdminSeq%22%3A%22(?P<value>\d+)~',
            '~sellerAdminSeq%22%3A(?P<value>\d+)~',
        ];
    }

    private function detectSellerAdminSeqFromHtml(string $html): string
    {
        foreach ($this->sellerAdminSeqPatterns() as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                $detected = trim((string)($matches['value'] ?? $matches[1] ?? ''));
                if ($detected !== '') {
                    return $detected;
                }
            }
        }

        return '';
    }

    private function detectSellerAdminSeqFromMtopProbe(string $productId): string
    {
        try {
            $decoded = $this->call(self::TOKEN_SEED_API, [
                'productId'      => $productId,
                'page'           => 1,
                'pageSize'       => 1,
                '_lang'          => $this->lang,
                'filter'         => 'all',
                'sort'           => 'complex_default',
                'country'        => $this->country,
                'sellerAdminSeq' => '0',
                'clientType'     => 'web',
            ]);
        } catch (RuntimeException) {
            return '';
        }

        return $this->extractSellerAdminSeqFromArray($decoded);
    }

    private function extractSellerAdminSeqFromArray(array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nested = $this->extractSellerAdminSeqFromArray($value);
                if ($nested !== '') {
                    return $nested;
                }
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $normalizedKey = strtolower((string)$key);
            if ($normalizedKey === 'selleradminseq' || $normalizedKey === 'ownermemberid') {
                $candidate = trim((string)$value);
                if ($candidate !== '' && preg_match('~^\d+$~', $candidate) === 1) {
                    return $candidate;
                }
            }
            if (is_string($value)) {
                $candidate = $this->detectSellerAdminSeqFromHtml($value);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }
}
