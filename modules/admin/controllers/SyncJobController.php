<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\enums\SyncJobStatusEnum;
use app\models\SyncJob;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class SyncJobController extends Controller
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

    public function actionIndex(?string $status = null, ?string $type = null): string
    {
        $query = SyncJob::find()->orderBy(['id' => SORT_DESC]);
        if ($status !== null && $status !== '') {
            $query->andWhere(['status' => $status]);
        }
        if ($type !== null && $type !== '') {
            $query->andWhere(['type' => $type]);
        }

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider(['query' => $query]),
            'status' => $status,
            'type' => $type,
        ]);
    }

    public function actionRetry(int $id): Response
    {
        $job = SyncJob::findOne($id) ?? throw new NotFoundHttpException('Job not found.');
        $job->status = SyncJobStatusEnum::PENDING->value;
        $job->available_at = time();
        $job->error_message = null;
        $job->save(false);
        Yii::$app->session->setFlash('success', "Job #{$id} re-queued.");

        return $this->redirect(['index', 'status' => 'pending']);
    }
}
