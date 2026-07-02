<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Product;
use app\models\ProductClick;
use app\services\CatalogQuery;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class GoController extends Controller
{
    public function actionIndex(int $id): Response
    {
        // Excludes products under a hidden (inactive) category, matching the product page.
        $product = CatalogQuery::active()->andWhere(['product.id' => $id])->one();
        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }
        $target = $product->affiliate_url ?: $product->product_url;
        if (!is_string($target) || $target === '') {
            throw new NotFoundHttpException('No outbound link for this product.');
        }

        $click = new ProductClick();
        $click->product_id = $product->id;
        $click->referrer = mb_substr((string)Yii::$app->request->referrer, 0, 1024) ?: null;
        $ua = (string)Yii::$app->request->userAgent;
        $click->ua_hash = $ua !== '' ? hash('sha256', $ua) : null;
        $click->save(false);

        Product::updateAllCounters(['click_count' => 1], ['id' => $product->id]);

        return $this->redirect($target, 302);
    }
}
