<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\enums\SyncJobStatusEnum;
use app\enums\SyncJobTypeEnum;
use app\models\SyncJob;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class SyncJobController extends Controller
{
    public function actionIndex(?string $status = null, ?string $type = null): string
    {
        $status = (string)$status;
        $type = (string)$type;

        $query = SyncJob::find();
        if ($status !== '' && SyncJobStatusEnum::tryFrom($status) !== null) {
            $query->andWhere(['status' => $status]);
        }
        if ($type !== '' && SyncJobTypeEnum::tryFrom($type) !== null) {
            $query->andWhere(['type' => $type]);
        }

        $statusOptions = [];
        foreach (SyncJobStatusEnum::cases() as $case) {
            $statusOptions[$case->value] = $case->value;
        }
        $typeOptions = [];
        foreach (SyncJobTypeEnum::cases() as $case) {
            $typeOptions[$case->value] = $case->value;
        }

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider([
                'query' => $query,
                'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            ]),
            'status' => $status,
            'type' => $type,
            'statusOptions' => $statusOptions,
            'typeOptions' => $typeOptions,
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
