<?php

declare(strict_types=1);

namespace app\components;

use yii\data\Pagination;
use yii\web\View;

final class Seo
{
    /**
     * Brand name appended to page titles. Fixed on purpose: `site.name`
     * ("Jewelry") is the product-category label shown in the header/footer/
     * catalog, whereas this is the actual store brand for the SERP title.
     */
    private const BRAND = 'SnagLoft';

    public static function apply(View $view, string $title, string $description, string $canonical, bool $noindex = false, string $ogImage = '', ?Pagination $pages = null): void
    {
        // Paginated listings self-canonicalise: page 2+ gets its own `?page=N`
        // canonical (never collapsed to page 1, or deep products fall out of the
        // index) and a "– Page N" title/description suffix so the SERP entries
        // aren't near-duplicates. Page 1 stays on the clean, param-free URL.
        if ($pages !== null && $pages->page > 0) {
            $n = $pages->page + 1;
            $title .= ' – Page ' . $n;
            $description = trim($description) . ' (page ' . $n . ')';
            $canonical .= (str_contains($canonical, '?') ? '&' : '?') . 'page=' . $n;
        }
        $title = self::withBrand($title);
        $view->title = $title;
        $desc = mb_substr(trim($description), 0, 300);
        $view->registerMetaTag(['name' => 'description', 'content' => $desc]);
        $view->registerLinkTag(['rel' => 'canonical', 'href' => $canonical]);
        if ($noindex) {
            $view->registerMetaTag(['name' => 'robots', 'content' => 'noindex,follow']);
        }
        $view->registerMetaTag(['property' => 'og:title', 'content' => $title]);
        $view->registerMetaTag(['property' => 'og:description', 'content' => $desc]);
        $view->registerMetaTag(['property' => 'og:type', 'content' => 'website']);
        if ($ogImage !== '') {
            $view->registerMetaTag(['property' => 'og:image', 'content' => $ogImage]);
        }
    }

    /**
     * Append the brand as a "Page title – SnagLoft" suffix. Skipped when the
     * page title is empty, already carries the brand, or the combined string
     * would run past Google's ~60-char SERP cut (long humanised AE names) — in
     * which case the bare title stays and reads cleaner than a truncated brand.
     */
    private static function withBrand(string $title): string
    {
        $brand = self::BRAND;
        $title = trim($title);
        if ($title === '' || $title === $brand) {
            return $title;
        }
        if (mb_stripos($title, $brand) !== false) {
            return $title;
        }
        $withBrand = $title . ' – ' . $brand;

        return mb_strlen($withBrand) <= 60 ? $withBrand : $title;
    }
}
