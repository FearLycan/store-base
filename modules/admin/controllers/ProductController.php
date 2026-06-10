<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\enums\SyncJobTypeEnum;
use app\models\Product;
use app\models\SyncJob;
use app\modules\admin\models\ImportProductForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class ProductController extends Controller
{
    public function actionIndex(?int $store_id = null): string
    {
        $query = Product::find()->with('store')->orderBy(['id' => SORT_DESC]);
        if ($store_id !== null) {
            $query->andWhere(['store_id' => $store_id]);
        }

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider(['query' => $query]),
            'storeId' => $store_id,
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['product' => $this->findProduct($id)]);
    }

    public function actionImport(): Response|string
    {
        $model = new ImportProductForm();
        if ($model->load(Yii::$app->request->post())) {
            $count = $model->save();
            if ($count > 0) {
                Yii::$app->session->setFlash('success', "Queued {$count} product(s) for import.");

                return $this->redirect(['index']);
            }
            if (!$model->hasErrors()) {
                Yii::$app->session->setFlash('warning', 'No new products to queue (already present or unrecognized).');
            }
        }

        return $this->render('import', ['model' => $model]);
    }

    public function actionRefresh(int $id): Response
    {
        $product = $this->findProduct($id);
        SyncJob::enqueue(SyncJobTypeEnum::PRODUCT_DETAIL, $product->store_id, $product->id, ['external_id' => $product->external_id]);
        Yii::$app->session->setFlash('success', 'Refresh queued.');

        return $this->redirect(['view', 'id' => $id]);
    }

    private function findProduct(int $id): Product
    {
        return Product::findOne($id) ?? throw new NotFoundHttpException('Product not found.');
    }
}
