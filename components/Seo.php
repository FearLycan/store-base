<?php

declare(strict_types=1);

namespace app\components;

use yii\web\View;

final class Seo
{
    /**
     * Brand name appended to page titles. Fixed on purpose: `site.name`
     * ("Jewelry") is the product-category label shown in the header/footer/
     * catalog, whereas this is the actual store brand for the SERP title.
     */
    private const BRAND = 'SnagLoft';

    public static function apply(View $view, string $title, string $description, string $canonical, bool $noindex = false, string $ogImage = ''): void
    {
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
