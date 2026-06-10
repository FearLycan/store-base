<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\enums\SyncJobStatusEnum;
use app\models\Category;
use app\models\Product;
use app\models\ProductClick;
use app\models\ProductReview;
use app\models\ProductVariant;
use app\models\Store;
use app\models\SyncJob;
use app\models\User;
use yii\web\Controller;

final class DashboardController extends Controller
{
    public function actionIndex(): string
    {
        $pending = (int)SyncJob::find()
            ->where(['status' => SyncJobStatusEnum::PENDING->value])
            ->count();

        $cards = [
            ['label' => 'Products', 'value' => (int)Product::find()->count(), 'icon' => 'product', 'url' => ['/admin/product/index']],
            ['label' => 'Stores', 'value' => (int)Store::find()->count(), 'icon' => 'store', 'url' => ['/admin/store/index']],
            ['label' => 'Categories', 'value' => (int)Category::find()->count(), 'icon' => 'category'],
            ['label' => 'Variants', 'value' => (int)ProductVariant::find()->count(), 'icon' => 'variant'],
            ['label' => 'Reviews', 'value' => (int)ProductReview::find()->count(), 'icon' => 'review'],
            ['label' => 'Clicks', 'value' => (int)ProductClick::find()->count(), 'icon' => 'click'],
            [
                'label' => 'Sync queue',
                'value' => (int)SyncJob::find()->count(),
                'icon' => 'sync',
                'url' => ['/admin/sync-job/index', 'status' => SyncJobStatusEnum::PENDING->value],
                'badge' => $pending > 0 ? $pending . ' pending' : null,
            ],
            ['label' => 'Users', 'value' => (int)User::find()->count(), 'icon' => 'users'],
        ];

        return $this->render('index', ['cards' => $cards]);
    }
}
