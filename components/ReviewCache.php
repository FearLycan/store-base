<?php

declare(strict_types=1);

namespace app\components;

use yii\caching\FileCache;

/**
 * A FileCache stored OUTSIDE the app's `cache` component, under @runtime/review-cache.
 * Because `yii cache/flush-all` only discovers caches registered in Yii::$app components,
 * this one is never touched by it — deliberately, since its entries are expensive to
 * rebuild (each miss hits AliExpress). Clear it explicitly via `yii review/flush-cache`.
 */
final class ReviewCache
{
    private static ?FileCache $instance = null;

    public static function get(): FileCache
    {
        if (self::$instance === null) {
            self::$instance = new FileCache([
                'cachePath'          => '@runtime/review-cache',
                'directoryLevel'     => 1,
                'defaultDuration'    => 0, // callers pass explicit TTL
                'gcProbability'      => 100,
                // The web app (www-data) is the only writer, but console commands run as
                // root under `docker exec` and can create this dir first. World-writable
                // dir + file modes let either user write regardless of who created it —
                // otherwise a root-owned dir silently blocks www-data and nothing caches.
                // Matches the container's already-777 @runtime posture.
                'dirMode'            => 0777,
                'fileMode'           => 0666,
            ]);
        }

        return self::$instance;
    }
}
