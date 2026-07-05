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
            ]);
        }

        return self::$instance;
    }
}
