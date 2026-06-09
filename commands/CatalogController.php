<?php

declare(strict_types=1);

namespace app\commands;

use app\models\Category;
use app\models\Product;
use yii\console\Controller;
use yii\console\ExitCode;

final class CatalogController extends Controller
{
    /** One-off: fill slug for rows created before SluggableBehavior existed (re-save triggers it). */
    public function actionBackfillSlugs(): int
    {
        $products = 0;
        foreach (Product::find()->where(['or', ['slug' => null], ['slug' => '']])->each(200) as $product) {
            if ($product->save()) { $products++; }
        }
        $categories = 0;
        foreach (Category::find()->where(['or', ['slug' => null], ['slug' => '']])->each(200) as $category) {
            if ($category->save()) { $categories++; }
        }
        $this->stdout("Backfilled {$products} product slug(s), {$categories} category slug(s).\n");

        return ExitCode::OK;
    }
}
