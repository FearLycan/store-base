<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Product;
use app\services\CatalogQuery;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

final class ProductController extends Controller
{
    public $layout = 'shop';

    public function actionView(string $slug): string
    {
        // CatalogQuery::active() already excludes products under a hidden (inactive) category,
        // so a direct link to one 404s just like its category page does.
        $product = CatalogQuery::active()
            ->andWhere(['slug' => $slug])
            ->with(['images', 'variants', 'specs', 'reviews.images', 'category', 'store'])
            ->one();
        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        return $this->render('view', ['product' => $product, 'related' => CatalogQuery::related($product, 8)]);
    }
}
