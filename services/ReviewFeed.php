<?php

declare(strict_types=1);

namespace app\services;

use app\components\aliexpress\AliExpressReviewScraper;
use app\components\ReviewCache;
use Throwable;

/**
 * On-demand, cached review feed backed by AliExpress. Each (product, filter, sort, page)
 * response is cached in the isolated ReviewCache; only misses hit AE.
 */
final class ReviewFeed
{
    private const TTL = 43200;      // 12h
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly AliExpressReviewScraper $scraper = new AliExpressReviewScraper(),
    ) {
    }

    /** Whitelist the filter token so a client can't push arbitrary strings at AE. */
    public static function normalizeFilter(string $filter): string
    {
        $filter = trim($filter);
        if ($filter === '' || $filter === 'all') {
            return 'all';
        }
        if (in_array($filter, ['image', 'additional', '1', '2', '3', '4', '5'], true)) {
            return $filter;
        }
        if (preg_match('~^impression:\d+$~', $filter) === 1) {
            return $filter;
        }

        return 'all';
    }

    public static function normalizeSort(string $sort): string
    {
        // Only tokens verified in Task 8 are allowed; default is AE's own ordering.
        return in_array($sort, ['complex_default'], true) ? $sort : 'complex_default';
    }

    /**
     * @return array{ok:bool, cards:array, images:string[], captions:array, page:int, totalPage:int, hasMore:bool, total:int}
     */
    public function page(string $externalProductId, string $filter, string $sort, int $page): array
    {
        $filter = self::normalizeFilter($filter);
        $sort = self::normalizeSort($sort);
        $page = max(1, min($page, 200)); // hard ceiling; AE tops out ~73 pages anyway

        $key = "rev:{$externalProductId}:{$filter}:{$sort}:{$page}";
        $cache = ReviewCache::get();
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached + ['ok' => true];
        }

        try {
            $res = $this->scraper->fetchPage($externalProductId, $filter, $sort, $page, self::PAGE_SIZE);
        } catch (Throwable $e) {
            return ['ok' => false, 'cards' => [], 'images' => [], 'captions' => [], 'page' => $page, 'totalPage' => 1, 'hasMore' => false, 'total' => 0];
        }

        $cards = [];
        $images = [];
        $captions = [];
        foreach ($res['reviews'] as $item) {
            $card = ReviewCardMapper::fromApiItem($item);
            $imgIdx = [];
            foreach ($card['images'] as $url) {
                $gi = count($images);
                $imgIdx[] = $gi;
                $images[] = $url;
                $captions[$gi] = ['n' => $card['name'], 'f' => $card['flag'], 'r' => $card['rating'], 'd' => $card['date'], 'c' => $card['content']];
            }
            $cards[] = $card;
        }

        $payload = [
            'cards'     => $cards,
            'images'    => $images,
            'captions'  => $captions,
            'page'      => $res['page'],
            'totalPage' => $res['totalPage'],
            'hasMore'   => $res['page'] < $res['totalPage'],
            'total'     => $res['total'],
        ];
        $cache->set($key, $payload, self::TTL);

        return $payload + ['ok' => true];
    }
}
