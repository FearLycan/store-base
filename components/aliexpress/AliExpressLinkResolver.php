<?php

namespace app\components\aliexpress;

final class AliExpressLinkResolver
{
    private const SHORT_HOST = 's.click.aliexpress.com';

    public function resolve(string $inputUrl): string
    {
        $normalizedUrl = $this->normalizeUrl($inputUrl);
        if ($normalizedUrl === null) {
            return '';
        }

        if (!$this->isShortLink($normalizedUrl)) {
            return $normalizedUrl;
        }

        return $this->resolveShortLink($normalizedUrl) ?? $normalizedUrl;
    }

    public function extractItemId(string $url): ?string
    {
        if (preg_match('~(?:/item/|/i/)(\d+)\.html~i', $url, $matches) === 1) {
            return $matches[1];
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            foreach (['itemId', 'item_id', 'productId', 'product_id'] as $key) {
                $candidate = $params[$key] ?? null;
                if (is_scalar($candidate) && preg_match('~^\d{8,}$~', (string)$candidate) === 1) {
                    return (string)$candidate;
                }
            }
        }

        return null;
    }

    private function normalizeUrl(string $inputUrl): ?string
    {
        $url = trim($inputUrl);
        if ($url === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $url) !== 1) {
            $url = 'https://' . ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    private function isShortLink(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));

        return $host === self::SHORT_HOST;
    }

    private function resolveShortLink(string $url): ?string
    {
        return $this->resolveWithCurl($url);
    }

    private function resolveWithCurl(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'BrickAtlasOfferImporter/1.0',
        ]);

        curl_exec($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return is_string($effectiveUrl) && $effectiveUrl !== '' ? $effectiveUrl : null;
    }
}
