<?php

declare(strict_types=1);

namespace app\components\aliexpress;

/**
 * Fetches and normalizes product reviews via the mtop review endpoint.
 * Logic ported from the prior project's AliExpressReviewController (commit 431faad),
 * stripped of console output and DB writes. Output is shaped for ProductReview::syncByProduct().
 */
final class AliExpressReviewScraper
{
    private const REVIEW_API = 'mtop.aliexpress.review.pc.list';

    public function __construct(private readonly AliExpressMtopSession $session = new AliExpressMtopSession())
    {
    }

    /**
     * @return array{reviews: array<int,array>, impressions: array<int,array{label:string,num:int}>}
     */
    public function fetch(string $productId, string $sellerAdminSeq = '', int $pages = 1, int $pageSize = 20): array
    {
        $this->session->bootstrapForProduct($productId);
        if ($sellerAdminSeq === '') {
            $sellerAdminSeq = $this->session->resolveSellerAdminSeq($productId);
        }
        if ($sellerAdminSeq === '') {
            $sellerAdminSeq = '0';
        }

        $all = [];
        $impressions = [];
        $normalizedPages = max(1, $pages);
        $normalizedPageSize = min(50, max(1, $pageSize));

        for ($page = 1; $page <= $normalizedPages; $page++) {
            $decoded = $this->session->call(self::REVIEW_API, [
                'productId'      => $productId,
                'page'           => $page,
                'pageSize'       => $normalizedPageSize,
                '_lang'          => $this->session->getLang(),
                'filter'         => 'all',
                'sort'           => 'complex_default',
                'country'        => $this->session->getCountry(),
                'sellerAdminSeq' => $sellerAdminSeq,
                'clientType'     => 'web',
            ]);
            $all = array_merge($all, $this->extractReviewSummaries($decoded));
            if ($impressions === []) {
                $impressions = $this->extractImpressionItems($decoded);
            }
        }

        return [
            'reviews'     => $this->mapExtractedReviewsToReviewItems($this->dedupeExtractedReviews($all)),
            'impressions' => $impressions,
        ];
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
        $reviewerName = $this->extractScalarStringByKeys($evaViewItem, ['buyerName', 'nickName', 'anonymousNickName', 'userName']);
        $reviewerCountry = $this->extractReviewerCountry($evaViewItem);

        if ($content === '' && $ratingValue === null && $images === [] && $reviewId === null) {
            return [];
        }

        return [
            'id'               => $reviewId,
            'rating_value'     => $ratingValue,
            'rating_scale_max' => $ratingScaleMax,
            'eval_date'        => $this->extractScalarStringByKeys($evaViewItem, ['evalDate']),
            'content'          => $content !== '' ? $content : null,
            'images'           => $images,
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

    private function extractReviewerCountry(array $data): ?string
    {
        $raw = $this->extractScalarStringByKeys($data, [
            'buyerCountry', 'countryCode', 'country', 'buyerCountryCode', 'displayCountry', 'region',
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
            return ['value' => round($raw / 20.0, 1), 'max' => 5.0];
        }
        if ($raw > 5.0 && $raw <= 10.0) {
            return ['value' => round($raw / 2.0, 1), 'max' => 5.0];
        }
        if ($raw >= 0.0 && $raw <= 5.0) {
            return ['value' => round($raw, 1), 'max' => 5.0];
        }

        return ['value' => null, 'max' => null];
    }

    /** Returns a unix timestamp (int) or null, to match product_review.reviewed_at (integer column). */
    private function normalizeReviewedAt(?string $evalDate): ?int
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

            return $timestamp > 0 ? $timestamp : null;
        }
        $timestamp = strtotime($normalized);

        return $timestamp === false ? null : $timestamp;
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
            $result[] = ['label' => $label, 'num' => max(0, $num)];
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
