<?php

declare(strict_types=1);

namespace app\components;

use yii\web\View;

final class Seo
{
    public static function apply(View $view, string $title, string $description, string $canonical, bool $noindex = false, string $ogImage = ''): void
    {
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
}
