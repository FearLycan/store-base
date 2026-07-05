<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Product;
use app\services\CatalogQuery;
use app\services\ReviewFeed;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

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

    public function actionReviews(int $id): Response
    {
        $product = CatalogQuery::active()->andWhere(['id' => $id])->one();
        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        $req = Yii::$app->request;
        $feed = (new ReviewFeed())->page(
            (string)$product->external_id,
            (string)$req->get('filter', 'all'),
            (string)$req->get('sort', 'complex_default'),
            (int)$req->get('page', 1),
        );

        $html = $this->renderPartial('_review-cards', [
            'cards'   => $feed['cards'],
            'imgBase' => (int)$req->get('imgBase', 0),
        ]);

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->data = [
            'ok'        => $feed['ok'],
            'html'      => $html,
            'images'    => $feed['images'],
            'captions'  => $feed['captions'],
            'page'      => $feed['page'],
            'totalPage' => $feed['totalPage'],
            'hasMore'   => $feed['hasMore'],
            'total'     => $feed['total'],
        ];

        return $response;
    }
}
