<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\enums\StoreStatusEnum;
use app\models\Store;
use app\models\StoreBanner;
use app\modules\admin\models\AddStoreForm;
use app\modules\admin\models\EditStoreForm;
use app\modules\admin\models\StoreBannerForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

final class StoreController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'banner-add'    => ['post'],
                    'banner-toggle' => ['post'],
                    'banner-delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(?string $q = null, ?string $status = null): string
    {
        $query = Store::find();

        $q = trim((string)$q);
        if ($q !== '') {
            $query->andWhere(['like', 'name', $q]);
        }

        $status = (string)$status;
        if ($status !== '' && StoreStatusEnum::tryFrom($status) !== null) {
            $query->andWhere(['status' => $status]);
        }

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider([
                'query' => $query,
                'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            ]),
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function actionAdd(): Response|string
    {
        $model = new AddStoreForm();
        if ($model->load(Yii::$app->request->post()) && ($store = $model->save()) !== null) {
            Yii::$app->session->setFlash('success', 'Store saved.');

            return $this->redirect(['view', 'id' => $store->id]);
        }

        return $this->render('add', ['model' => $model]);
    }

    public function actionEdit(int $id): Response|string
    {
        $store = $this->findStore($id);
        $model = new EditStoreForm($store);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Store updated.');

            return $this->redirect(['view', 'id' => $store->id]);
        }

        return $this->render('edit', [
            'model'      => $model,
            'store'      => $store,
            'banners'    => $this->storeBanners($store),
            'bannerForm' => new StoreBannerForm(),
        ]);
    }

    public function actionBannerAdd(int $id): Response|string
    {
        $store = $this->findStore($id);
        $form = new StoreBannerForm();
        $form->load(Yii::$app->request->post());
        $form->file = UploadedFile::getInstance($form, 'file');
        if ($form->apply($store) !== null) {
            Yii::$app->session->setFlash('success', 'Banner added.');

            return $this->redirect(['edit', 'id' => $store->id]);
        }

        // Re-render the edit page with the banner form's validation errors.
        return $this->render('edit', [
            'model'      => new EditStoreForm($store),
            'store'      => $store,
            'banners'    => $this->storeBanners($store),
            'bannerForm' => $form,
        ]);
    }

    public function actionBannerToggle(int $id): Response
    {
        $banner = $this->findBanner($id);
        $banner->status = $banner->isActive() ? StoreBanner::STATUS_HIDDEN : StoreBanner::STATUS_ACTIVE;
        $banner->save(false, ['status', 'updated_at']);

        return $this->redirect(['edit', 'id' => $banner->store_id]);
    }

    public function actionBannerDelete(int $id): Response
    {
        $banner = $this->findBanner($id);
        $storeId = $banner->store_id;
        StoreBannerForm::deleteBanner($banner);
        Yii::$app->session->setFlash('success', 'Banner deleted.');

        return $this->redirect(['edit', 'id' => $storeId]);
    }

    public function actionView(int $id): string
    {
        $store = $this->findStore($id);

        return $this->render('view', [
            'store' => $store,
            'products' => new ActiveDataProvider([
                'query' => $store->getProducts(),
                'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
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

    /**
     * All banners of a store (hidden included) for the admin list, in display order.
     *
     * @return StoreBanner[]
     */
    private function storeBanners(Store $store): array
    {
        return StoreBanner::find()
            ->where(['store_id' => $store->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    private function findBanner(int $id): StoreBanner
    {
        return StoreBanner::findOne($id) ?? throw new NotFoundHttpException('Banner not found.');
    }

    private function findStore(int $id): Store
    {
        return Store::findOne($id) ?? throw new NotFoundHttpException('Store not found.');
    }
}
