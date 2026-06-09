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
        $product = Product::find()
            ->where(['slug' => $slug, 'status' => 'active'])
            ->with(['images', 'variants', 'specs', 'reviews.images', 'category', 'store'])
            ->one();
        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        return $this->render('view', ['product' => $product, 'related' => CatalogQuery::related($product, 8)]);
    }
}
