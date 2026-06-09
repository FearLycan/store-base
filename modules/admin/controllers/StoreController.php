<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\enums\StoreStatusEnum;
use app\enums\SyncJobTypeEnum;
use app\models\Store;
use app\models\SyncJob;
use app\modules\admin\models\AddStoreForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class StoreController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider([
                'query' => Store::find()->orderBy(['id' => SORT_DESC]),
            ]),
        ]);
    }

    public function actionAdd(): Response|string
    {
        $model = new AddStoreForm();
        if ($model->load(Yii::$app->request->post()) && ($store = $model->save()) !== null) {
            Yii::$app->session->setFlash('success', 'Store saved; discovery queued.');

            return $this->redirect(['view', 'id' => $store->id]);
        }

        return $this->render('add', ['model' => $model]);
    }

    public function actionView(int $id): string
    {
        $store = $this->findStore($id);

        return $this->render('view', [
            'store' => $store,
            'products' => new ActiveDataProvider([
                'query' => $store->getProducts()->orderBy(['id' => SORT_DESC]),
            ]),
        ]);
    }

    public function actionPause(int $id): Response
    {
        $store = $this->findStore($id);
        $store->status = StoreStatusEnum::PAUSED->value;
        $store->save(false, ['status']);

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionResume(int $id): Response
    {
        $store = $this->findStore($id);
        $store->status = StoreStatusEnum::ACTIVE->value;
        $store->save(false, ['status']);

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionSyncNow(int $id): Response
    {
        $store = $this->findStore($id);
        SyncJob::enqueue(SyncJobTypeEnum::STORE_DISCOVERY, $store->id, null);
        Yii::$app->session->setFlash('success', 'Discovery queued.');

        return $this->redirect(['view', 'id' => $id]);
    }

    private function findStore(int $id): Store
    {
        return Store::findOne($id) ?? throw new NotFoundHttpException('Store not found.');
    }
}
