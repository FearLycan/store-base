<?php

namespace app\commands;

use common\enums\StatusEnum;
use common\models\SetOffer;
use common\models\SetOfferReview;
use common\models\Store;
use RuntimeException;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;
use yii\httpclient\Client;

final class AliExpressReviewController extends Controller
{
    public string $mtopToken = '';
    public string $mtopTokenEnc = '';
    public string $cookie = '';
    public string $lang = 'en_US';
    public string $country = 'US';
    public string $filter = 'all';
    public string $sort = 'complex_default';
    public string $appKey = '12574478';
    public bool $autoBootstrap = true;
    public int $setOfferId = 0;
    public bool $saveToDb = true;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'mtopToken',
            'mtopTokenEnc',
            'cookie',
            'lang',
            'country',
            'filter',
            'sort',
            'appKey',
            'autoBootstrap',
            'setOfferId',
            'saveToDb',
        ]);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            't' => 'mtopToken',
            'e' => 'mtopTokenEnc',
            'c' => 'cookie',
            'l' => 'lang',
            'k' => 'country',
            'a' => 'autoBootstrap',
            's' => 'setOfferId',
        ]);
    }

    public function actionSync(): void
    {
        $store = Store::findOne(['code' => 'ALIEXPRESS']);
        if (!$store) {
            $this->stderr("Store ALIEXPRESS not found.\n");
        }

        $date = new \DateTime('now');
        $date->modify('-30 days');

        $setOffers = SetOffer::find()
            ->limit(200)
            ->where(['store_id' => $store->id])
            ->andWhere([
                'or',
                ['last_review_synced_at' => null],
                ['<=', 'last_review_synced_at', $date->format('Y-m-d H:i:s')],
            ]);

        /** @var SetOffer $setOffer */
        foreach ($setOffers->each(50) as $setOffer) {
            $setOffer->updateAttributes(['last_review_synced_at' => date('Y-m-d H:i:s')]);
            $this->stdout("Processing set_offer_id={$setOffer->id}...\n");
            try {
                $this->actionFetch($setOffer->id);
            } catch (Throwable $e) {
                $this->stderr("set_offer_id={$setOffer->id} failed: {$e->getMessage()}\n");
                Yii::error("AliExpressReviewController::actionSync failed for offer {$setOffer->id}: {$e->getMessage()}", __METHOD__);
            }
            sleep(random_int(2, 8));
        }
    }

    public function actionFetch(int $offerId, string $sellerAdminSeq = '', int $pages = 1, int $pageSize = 20): int
    {
        $setOffer = SetOffer::findOne($offerId);

        if (!$setOffer) {
            return ExitCode::IOERR;
        }

        if ($setOffer->store->code !== 'ALIEXPRESS') {
            return ExitCode::IOERR;
        }

        $normalizedPages = max(1, $pages);
        $normalizedPageSize = min(50, max(1, $pageSize));
        $normalizedProductId = trim($setOffer->external_id);
        if ($normalizedProductId === '') {
            $this->stderr("productId is required.\n");

            return ExitCode::USAGE;
        }

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $cookieHeader = $this->buildCookieHeader();
        $productPageHtml = '';

        if ($cookieHeader === '' && $this->autoBootstrap) {
            $this->stdout("No cookie/token provided, bootstrapping AliExpress session...\n");
            [$productPageHtml, $cookieHeader] = $this->bootstrapSessionFromProductPage($client, $normalizedProductId);
            $tokenBootstrapCookie = $this->bootstrapMtopTokenCookie($client, $normalizedProductId, $cookieHeader);
            if ($tokenBootstrapCookie !== '') {
                $cookieHeader = $this->mergeCookieHeaders($cookieHeader, $tokenBootstrapCookie);
            }

            $bootstrapToken = $this->resolveToken($cookieHeader);
            if ($bootstrapToken !== '') {
                $this->stdout("Session bootstrap succeeded.\n");
            } else {
                $this->stdout("Session bootstrap finished, but _m_h5_tk was not issued.\n");
            }
        }

        $token = $this->resolveToken($cookieHeader);
        if ($token === '') {
            $this->stderr("Missing _m_h5_tk token. Pass --mtopToken or --cookie with _m_h5_tk value, or keep --auto-bootstrap=1.\n");

            return ExitCode::USAGE;
        }

        $normalizedSellerAdminSeq = $this->resolveSellerAdminSeq(
            $client,
            $normalizedProductId,
            trim($sellerAdminSeq),
            $productPageHtml,
            $cookieHeader,
            $token
        );
        if ($normalizedSellerAdminSeq === '') {
            $normalizedSellerAdminSeq = '0';
            $this->stdout("Could not resolve sellerAdminSeq automatically. Falling back to sellerAdminSeq=0 for probe requests.\n");
        }

        $this->stdout("Using token prefix: " . substr($token, 0, 8) . "***\n");
        $this->stdout("productId={$normalizedProductId}, sellerAdminSeq={$normalizedSellerAdminSeq}, pages={$normalizedPages}, pageSize={$normalizedPageSize}\n\n");
        $allExtractedReviews = [];
        $impressionItems = [];

        for ($page = 1; $page <= $normalizedPages; $page++) {
            $dataPayload = [
                'productId'      => $normalizedProductId,
                'page'           => $page,
                'pageSize'       => $normalizedPageSize,
                '_lang'          => $this->lang,
                'filter'         => $this->filter,
                'sort'           => $this->sort,
                'country'        => $this->country,
                'sellerAdminSeq' => $normalizedSellerAdminSeq,
                'clientType'     => 'web',
            ];
            $data = Json::encode($dataPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $timestamp = (string)((int)floor(microtime(true) * 1000));
            $sign = md5($token . '&' . $timestamp . '&' . $this->appKey . '&' . $data);
            $callback = 'mtopjsonp_debug_' . $page;

            $query = [
                'jsv'      => '2.5.1',
                'appKey'   => $this->appKey,
                't'        => $timestamp,
                'sign'     => $sign,
                'api'      => 'mtop.aliexpress.review.pc.list',
                'v'        => '1.0',
                'type'     => 'jsonp',
                'dataType' => 'jsonp',
                'callback' => $callback,
                'data'     => $data,
            ];

            $request = $client
                ->createRequest()
                ->setMethod('GET')
                ->setUrl('https://acs.aliexpress.com/h5/mtop.aliexpress.review.pc.list/1.0/');

            $headers = [
                'Accept'          => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
                'Referer'         => 'https://www.aliexpress.com/',
            ];

            if ($cookieHeader !== '') {
                $headers['Cookie'] = $cookieHeader;
            }

            $response = $request
                ->addHeaders($headers)
                ->setData($query)
                ->send();

            $this->stdout("=== PAGE {$page} HTTP {$response->statusCode} ===\n");
            $rawBody = (string)$response->getContent();
            $this->stdout("RAW:\n{$rawBody}\n\n");

            try {
                $decoded = $this->decodeJsonp($rawBody);
                $this->stdout("PARSED JSON:\n" . Json::encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n");
                $extractedReviews = $this->extractReviewSummaries($decoded);
                $this->stdout("EXTRACTED REVIEWS (rating, content, images):\n" . Json::encode($extractedReviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n");
                $allExtractedReviews = array_merge($allExtractedReviews, $extractedReviews);
                if ($impressionItems === []) {
                    $impressionItems = $this->extractImpressionItems($decoded);
                }
            } catch (RuntimeException $exception) {
                $this->stderr("JSON parse error on page {$page}: {$exception->getMessage()}\n\n");
            }
        }

        $dedupedReviews = $this->dedupeExtractedReviews($allExtractedReviews);
        $this->stdout("TOTAL EXTRACTED REVIEWS: " . count($dedupedReviews) . "\n");
        if ($this->saveToDb) {
            $reviewItems = $this->mapExtractedReviewsToReviewItems($dedupedReviews);
            SetOfferReview::syncByOffer($setOffer, $reviewItems, 'aliexpress_review');
            $setOffer->review_impressions = $impressionItems !== [] ? Json::encode($impressionItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $setOffer->save(false, ['review_impressions']);
            $this->stdout("Saved " . count($reviewItems) . " review records to DB for set_offer_id={$setOffer->id}.\n");
        }

        return ExitCode::OK;
    }

    public function stdout($string): string
    {
        if (YII_DEBUG) {
            parent::stdout($string);
        }

        return '';
    }

    private function resolveToken(string $cookieHeader = ''): string
    {
        if (trim($this->mtopToken) !== '') {
            return $this->extractTokenPrefix(trim($this->mtopToken));
        }

        $cookieSource = trim($this->cookie) !== '' ? $this->cookie : $cookieHeader;
        if (trim($cookieSource) !== '') {
            $cookieToken = $this->extractCookieValue($cookieSource, '_m_h5_tk');
            if ($cookieToken !== null) {
                return $this->extractTokenPrefix($cookieToken);
            }
        }

        return '';
    }

    private function buildCookieHeader(): string
    {
        $cookie = trim($this->cookie);
        if ($cookie !== '') {
            return $cookie;
        }

        $parts = [];
        if (trim($this->mtopToken) !== '') {
            $parts[] = '_m_h5_tk=' . trim($this->mtopToken);
        }
        if (trim($this->mtopTokenEnc) !== '') {
            $parts[] = '_m_h5_tk_enc=' . trim($this->mtopTokenEnc);
        }

        return implode('; ', $parts);
    }

    private function extractTokenPrefix(string $tokenValue): string
    {
        $separatorPos = strpos($tokenValue, '_');
        if ($separatorPos === false) {
            return $tokenValue;
        }

        return substr($tokenValue, 0, $separatorPos);
    }

    private function extractCookieValue(string $cookieHeader, string $name): ?string
    {
        $pattern = '~(?:^|;\s*)' . preg_quote($name, '~') . '=([^;]+)~';
        if (preg_match($pattern, $cookieHeader, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function decodeJsonp(string $rawBody): array
    {
        $trimmed = trim($rawBody);
        if ($trimmed === '') {
            throw new RuntimeException('Empty response body.');
        }

        if (preg_match('~^[^(]+\((.*)\)\s*;?\s*$~s', $trimmed, $matches) !== 1) {
            throw new RuntimeException('Response is not JSONP.');
        }

        $decoded = Json::decode($matches[1], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Decoded JSON is not an object/array.');
        }

        return $decoded;
    }

    private function resolveSellerAdminSeq(
        Client $client,
        string $productId,
        string $providedSellerAdminSeq,
        string $prefetchedHtml = '',
        string $cookieHeader = '',
        string $token = ''
    ): string
    {
        if ($providedSellerAdminSeq !== '') {
            return $providedSellerAdminSeq;
        }

        $this->stdout("sellerAdminSeq not provided, trying to detect from product page...\n");

        $html = $prefetchedHtml !== '' ? $prefetchedHtml : $this->fetchProductPageHtml($client, $productId, $cookieHeader);
        if ($html === '') {
            return '';
        }

        $detectedFromProductPage = $this->detectSellerAdminSeqFromHtml($html);
        if ($detectedFromProductPage !== '') {
            $this->stdout("Detected sellerAdminSeq={$detectedFromProductPage}\n");

            return $detectedFromProductPage;
        }

        $feedbackPageHtml = $this->fetchFeedbackPageHtml($client, $html, $cookieHeader);
        if ($feedbackPageHtml !== '') {
            $detectedFromFeedbackPage = $this->detectSellerAdminSeqFromHtml($feedbackPageHtml);
            if ($detectedFromFeedbackPage !== '') {
                $this->stdout("Detected sellerAdminSeq={$detectedFromFeedbackPage} (feedback page)\n");

                return $detectedFromFeedbackPage;
            }
        }

        if ($token !== '') {
            $detectedFromProbe = $this->detectSellerAdminSeqFromMtopProbe($client, $productId, $cookieHeader, $token);
            if ($detectedFromProbe !== '') {
                $this->stdout("Detected sellerAdminSeq={$detectedFromProbe} (mtop probe)\n");

                return $detectedFromProbe;
            }
        }

        return '';
    }

    private function fetchProductPageHtml(Client $client, string $productId, string $cookieHeader = ''): string
    {
        $productUrls = [
            'https://www.aliexpress.com/item/' . rawurlencode($productId) . '.html',
        ];
        $lastStatusCode = 0;

        foreach ($productUrls as $productUrl) {
            $request = $client
                ->createRequest()
                ->setMethod('GET')
                ->setUrl($productUrl)
                ->setOptions([
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 8,
                    CURLOPT_TIMEOUT        => 25,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);

            $headers = [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
                'Referer'         => 'https://www.aliexpress.com/',
            ];
            if ($cookieHeader !== '') {
                $headers['Cookie'] = $cookieHeader;
            }

            $response = $request->addHeaders($headers)->send();
            $lastStatusCode = (int)$response->statusCode;
            if ($response->isOk) {
                return (string)$response->getContent();
            }
        }

        $this->stderr("Failed to fetch product page for sellerAdminSeq detection: HTTP {$lastStatusCode}\n");

        return '';
    }

    private function bootstrapSessionFromProductPage(Client $client, string $productId): array
    {
        $response = $client
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

        $html = (string)$response->getContent();
        $setCookies = $response->headers->get('set-cookie', null, false);
        if (!is_array($setCookies)) {
            $setCookies = [];
        }

        $parsedCookies = $this->parseSetCookies($setCookies);
        $bootstrapCookieHeader = $this->buildCookieHeaderFromMap($parsedCookies);

        return [$html, $bootstrapCookieHeader];
    }

    private function bootstrapMtopTokenCookie(Client $client, string $productId, string $cookieHeader): string
    {
        $timestamp = (string)((int)floor(microtime(true) * 1000));
        $callback = 'mtopjsonp_bootstrap';
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

        $query = [
            'jsv'      => '2.5.1',
            'appKey'   => $this->appKey,
            't'        => $timestamp,
            'sign'     => md5('bootstrap-' . $timestamp),
            'api'      => 'mtop.aliexpress.review.pc.list',
            'v'        => '1.0',
            'type'     => 'jsonp',
            'dataType' => 'jsonp',
            'callback' => $callback,
            'data'     => $data,
        ];

        $request = $client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl('https://acs.aliexpress.com/h5/mtop.aliexpress.review.pc.list/1.0/')
            ->addHeaders([
                'Accept'          => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
                'Referer'         => 'https://www.aliexpress.com/',
                'Cookie'          => $cookieHeader,
            ])
            ->setData($query);

        $response = $request->send();
        $setCookies = $response->headers->get('set-cookie', null, false);
        if (!is_array($setCookies)) {
            $setCookies = [];
        }

        return $this->buildCookieHeaderFromMap($this->parseSetCookies($setCookies));
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

    private function fetchFeedbackPageHtml(Client $client, string $productPageHtml, string $cookieHeader): string
    {
        $feedbackUrl = $this->extractFeedbackUrlFromProductHtml($productPageHtml);
        if ($feedbackUrl === '') {
            return '';
        }

        $response = $client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl($feedbackUrl)
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
                'Cookie'          => $cookieHeader,
            ])
            ->send();

        if (!$response->isOk) {
            return '';
        }

        return (string)$response->getContent();
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

    private function detectSellerAdminSeqFromMtopProbe(Client $client, string $productId, string $cookieHeader, string $token): string
    {
        $timestamp = (string)((int)floor(microtime(true) * 1000));
        $callback = 'mtopjsonp_probe';
        $dataPayload = [
            'productId'      => $productId,
            'page'           => 1,
            'pageSize'       => 1,
            '_lang'          => $this->lang,
            'filter'         => $this->filter,
            'sort'           => $this->sort,
            'country'        => $this->country,
            'sellerAdminSeq' => '0',
            'clientType'     => 'web',
        ];
        $data = Json::encode($dataPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sign = md5($token . '&' . $timestamp . '&' . $this->appKey . '&' . $data);

        $response = $client
            ->createRequest()
            ->setMethod('GET')
            ->setUrl('https://acs.aliexpress.com/h5/mtop.aliexpress.review.pc.list/1.0/')
            ->addHeaders([
                'Accept'          => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
                'Referer'         => 'https://www.aliexpress.com/',
                'Cookie'          => $cookieHeader,
            ])
            ->setData([
                'jsv'      => '2.5.1',
                'appKey'   => $this->appKey,
                't'        => $timestamp,
                'sign'     => $sign,
                'api'      => 'mtop.aliexpress.review.pc.list',
                'v'        => '1.0',
                'type'     => 'jsonp',
                'dataType' => 'jsonp',
                'callback' => $callback,
                'data'     => $data,
            ])
            ->send();

        $rawBody = (string)$response->getContent();
        try {
            $decoded = $this->decodeJsonp($rawBody);
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
            if (
                $normalizedKey === 'selleradminseq' ||
                $normalizedKey === 'selleraadminseq' ||
                $normalizedKey === 'ownermemberid'
            ) {
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

    private function extractReviewSummaries(array $payload): array
    {
        $result = [];
        $evaViewList = $this->resolveEvaViewList($payload);
        if ($evaViewList === []) {
            return $result;
        }

        $seenIds = [];
        foreach ($evaViewList as $evaViewItem) {
            if (!is_array($evaViewItem)) {
                continue;
            }

            $normalized = $this->mapEvaViewItem($evaViewItem);
            if ($normalized === []) {
                continue;
            }

            $reviewId = isset($normalized['id']) ? trim((string)$normalized['id']) : '';
            if ($reviewId !== '' && isset($seenIds[$reviewId])) {
                continue;
            }

            if ($reviewId !== '') {
                $seenIds[$reviewId] = true;
            }
            $result[] = $normalized;
        }

        return $result;
    }

    private function resolveEvaViewList(array $payload): array
    {
        if (isset($payload['evaViewList']) && is_array($payload['evaViewList'])) {
            return $payload['evaViewList'];
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $resolved = $this->resolveEvaViewList($value);
                if ($resolved !== []) {
                    return $resolved;
                }
            }
        }

        return [];
    }

    private function mapEvaViewItem(array $evaViewItem): array
    {
        $reviewId = $this->extractReviewId($evaViewItem);
        $ratingRaw = $this->extractFloatByKeys($evaViewItem, ['buyerEval', 'rating', 'score']);
        ['value' => $ratingValue, 'max' => $ratingScaleMax] = $this->normalizeRatingToProjectScale($ratingRaw);
        $content = $this->extractScalarStringByKeys($evaViewItem, ['buyerTranslationFeedback']);
        $images = $this->extractImageUrlsFromEvaViewItem($evaViewItem);
        $reviewerId = $this->extractReviewerId($evaViewItem);
        $reviewerName = $this->extractScalarStringByKeys($evaViewItem, ['buyerName', 'nickName', 'anonymousNickName', 'userName']);
        $reviewerCountry = $this->extractReviewerCountry($evaViewItem);

        if ($content === '' && $ratingValue === null && $images === [] && $reviewId === null) {
            return [];
        }

        return [
            'id'               => $reviewId,
            'rating_raw'       => $ratingRaw,
            'rating_value'     => $ratingValue,
            'rating_scale_max' => $ratingScaleMax,
            'eval_date'        => $this->extractScalarStringByKeys($evaViewItem, ['evalDate']),
            'content'          => $content !== '' ? $content : null,
            'images'           => $images,
            'reviewer_id'      => $reviewerId,
            'reviewer_name'    => $reviewerName !== '' ? $reviewerName : null,
            'reviewer_country' => $reviewerCountry,
        ];
    }

    private function extractImageUrlsFromEvaViewItem(array $evaViewItem): array
    {
        $sources = [];
        foreach (['imageList', 'images', 'imageUrls', 'photoList', 'picList'] as $key) {
            if (!isset($evaViewItem[$key]) || !is_array($evaViewItem[$key])) {
                continue;
            }
            $sources[] = $evaViewItem[$key];
        }

        $urls = [];
        foreach ($sources as $sourceList) {
            foreach ($sourceList as $entry) {
                if (is_string($entry)) {
                    $normalized = $this->normalizeImageUrl($entry);
                    if ($normalized !== null) {
                        $urls[] = $normalized;
                    }
                    continue;
                }
                if (is_array($entry)) {
                    foreach (['imageUrl', 'url', 'image', 'imgUrl', 'photoUrl'] as $urlKey) {
                        if (!isset($entry[$urlKey]) || !is_scalar($entry[$urlKey])) {
                            continue;
                        }
                        $normalized = $this->normalizeImageUrl((string)$entry[$urlKey]);
                        if ($normalized !== null) {
                            $urls[] = $normalized;
                        }
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function extractScalarStringByKeys(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key])) {
                continue;
            }
            $normalized = trim((string)$data[$key]);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalizeImageUrl(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }
        if (!preg_match('~^https?://~i', $normalized)) {
            return null;
        }
        if (!preg_match('~\.(?:jpg|jpeg|png|webp)(?:\?|$)~i', $normalized)) {
            return null;
        }

        $normalized = preg_replace('~_(\d+)x(\d+)\.(jpg|jpeg|png|webp)(\?.*)?$~i', '.$3$4', $normalized) ?? $normalized;
        if (str_starts_with($normalized, 'http://')) {
            $normalized = 'https://' . substr($normalized, 7);
        }

        return $normalized;
    }

    private function dedupeExtractedReviews(array $reviews): array
    {
        $seenIds = [];
        $result = [];
        foreach ($reviews as $review) {
            if (!is_array($review)) {
                continue;
            }

            $reviewId = isset($review['id']) ? trim((string)$review['id']) : '';
            if ($reviewId !== '' && isset($seenIds[$reviewId])) {
                continue;
            }

            if ($reviewId !== '') {
                $seenIds[$reviewId] = true;
            }
            $result[] = $review;
        }

        return $result;
    }

    private function extractReviewId(array $data): ?string
    {
        foreach (['evaluationId', 'reviewId', 'id', 'feedbackId'] as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key])) {
                continue;
            }
            $candidate = trim((string)$data[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function extractReviewerId(array $data): ?string
    {
        foreach (['memberId', 'buyerId', 'userId', 'authorId', 'reviewerId', 'customerId'] as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key])) {
                continue;
            }

            $candidate = trim((string)$data[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function extractReviewerCountry(array $data): ?string
    {
        $raw = $this->extractScalarStringByKeys($data, [
            'buyerCountry',
            'countryCode',
            'country',
            'buyerCountryCode',
            'displayCountry',
            'region',
        ]);
        if ($raw === '') {
            return null;
        }

        $normalized = strtoupper(trim($raw));
        if (preg_match('~^[A-Z]{2,3}$~', $normalized) === 1) {
            return $normalized;
        }

        return $raw;
    }

    private function extractFloatByKeys(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                continue;
            }

            $value = $data[$key];
            if (is_numeric($value)) {
                return (float)$value;
            }
            if (is_string($value) && preg_match('~(-?\d+(?:[.,]\d+)?)~', $value, $matches) === 1) {
                return (float)str_replace(',', '.', $matches[1]);
            }
        }

        return null;
    }

    private function resolveSetOffer(string $productId): ?SetOffer
    {
        if ($this->setOfferId > 0) {
            return SetOffer::findOne($this->setOfferId);
        }

        $store = Store::findOne(['code' => 'ALIEXPRESS']);
        if (!$store) {
            return null;
        }

        return SetOffer::find()
            ->where([
                'store_id'    => $store->id,
                'external_id' => $productId,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    private function mapExtractedReviewsToReviewItems(array $reviews): array
    {
        $items = [];
        foreach ($reviews as $review) {
            if (!is_array($review)) {
                continue;
            }

            $items[] = [
                'external_review_id' => isset($review['id']) ? (string)$review['id'] : null,
                'author_name'        => isset($review['reviewer_name']) ? (string)$review['reviewer_name'] : null,
                'reviewer_country'   => isset($review['reviewer_country']) ? (string)$review['reviewer_country'] : null,
                'reviewed_at'        => $this->normalizeReviewedAt(isset($review['eval_date']) ? (string)$review['eval_date'] : null),
                'content'            => isset($review['content']) ? (string)$review['content'] : null,
                'rating_value'       => isset($review['rating_value']) && is_numeric($review['rating_value']) ? (float)$review['rating_value'] : null,
                'rating_scale_max'   => isset($review['rating_scale_max']) && is_numeric($review['rating_scale_max']) ? (float)$review['rating_scale_max'] : 5.0,
                'images'             => isset($review['images']) && is_array($review['images']) ? $review['images'] : [],
            ];
        }

        return $items;
    }

    private function normalizeRatingToProjectScale(?float $raw): array
    {
        if ($raw === null) {
            return ['value' => null, 'max' => null];
        }

        if ($raw > 5.0 && $raw <= 100.0) {
            return [
                'value' => round($raw / 20.0, 1),
                'max'   => 5.0,
            ];
        }

        if ($raw > 5.0 && $raw <= 10.0) {
            return [
                'value' => round($raw / 2.0, 1),
                'max'   => 5.0,
            ];
        }

        if ($raw >= 0.0 && $raw <= 5.0) {
            return [
                'value' => round($raw, 1),
                'max'   => 5.0,
            ];
        }

        return ['value' => null, 'max' => null];
    }

    private function normalizeReviewedAt(?string $evalDate): ?string
    {
        if ($evalDate === null) {
            return null;
        }

        $normalized = trim($evalDate);
        if ($normalized === '') {
            return null;
        }

        if (is_numeric($normalized)) {
            $timestamp = (int)$normalized;
            if ($timestamp > 1000000000000) {
                $timestamp = (int)floor($timestamp / 1000);
            }

            return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function extractImpressionItems(array $payload): array
    {
        $rawList = $this->resolveImpressionDtoList($payload);
        if ($rawList === []) {
            return [];
        }

        $result = [];
        foreach ($rawList as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $label = trim((string)($rawItem['title'] ?? $rawItem['name'] ?? $rawItem['content'] ?? ''));
            $num = isset($rawItem['num']) && is_numeric($rawItem['num'])
                ? (int)$rawItem['num']
                : (isset($rawItem['count']) && is_numeric($rawItem['count']) ? (int)$rawItem['count'] : 0);
            if ($label === '') {
                continue;
            }

            $result[] = [
                'label' => $label,
                'num'   => max(0, $num),
            ];
        }

        return $result;
    }

    private function resolveImpressionDtoList(array $payload): array
    {
        if (isset($payload['impressionDTOList']) && is_array($payload['impressionDTOList'])) {
            return $payload['impressionDTOList'];
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $resolved = $this->resolveImpressionDtoList($value);
            if ($resolved !== []) {
                return $resolved;
            }
        }

        return [];
    }
}
