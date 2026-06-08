<?php

namespace app\commands;

use common\components\aliexpress\AliExpressOfferImporter;
use common\enums\SetOfferImportStatusEnum;
use common\models\Set;
use common\models\SetOfferImport;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class SetOfferImportController extends Controller
{
    public function actionProcessPending(int $limit = 20): int
    {
        $pendingImportsQuery = SetOfferImport::find()
            ->where(['status' => SetOfferImportStatusEnum::PENDING->value])
            ->orderBy(['id' => SORT_ASC])
            ->limit(max(1, $limit));

        if (!$pendingImportsQuery->exists()) {
            $this->stdout("No pending imports.\n");

            return ExitCode::OK;
        }

        $importer = new AliExpressOfferImporter();
        $processedCount = 0;

        foreach ($pendingImportsQuery->each(50) as $task) {
            $claimed = SetOfferImport::updateAll(
                [
                    'status'     => SetOfferImportStatusEnum::PROCESSING->value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                [
                    'id'     => $task->id,
                    'status' => SetOfferImportStatusEnum::PENDING->value,
                ]
            );
            if ($claimed !== 1) {
                continue;
            }

            $task->refresh();

            $offer = null;
            try {
                $set = Set::findOne($task->set_id);
                if (!$set) {
                    throw new \RuntimeException('Set not found.');
                }

                $offer = $importer->importByUrl($set, $task->input_url);
                $task->status = SetOfferImportStatusEnum::DONE->value;
                $task->set_offer_id = $offer->id;
                $task->error_message = null;
                $task->attempts = (int)$task->attempts + 1;
                $task->processed_at = date('Y-m-d H:i:s');
                $task->save(false);
                $processedCount++;
                $this->stdout("Processed import #{$task->id}\n");
            } catch (Throwable $exception) {
                $task->status = SetOfferImportStatusEnum::FAILED->value;
                $task->attempts = (int)$task->attempts + 1;
                $task->error_message = mb_substr(trim($exception->getMessage()), 0, 1000);
                $task->save(false);
                $this->stderr("Failed import #{$task->id}: {$task->error_message}\n");
            }

            if ($offer !== null) {
                try {
                    $controller = new AliExpressReviewController(Yii::$app->controller->id, Yii::$app);
                    $controller->actionFetch($offer->id);
                } catch (Throwable $exception) {
                    $this->stderr("Review fetch for offer #{$offer->id} failed: {$exception->getMessage()}\n");
                    Yii::error("actionFetch failed for offer {$offer->id}: {$exception->getMessage()}", __METHOD__);
                }
            }

            sleep(random_int(2, 5));
        }

        $this->stdout("Done. Successful imports: {$processedCount}\n");

        return ExitCode::OK;
    }
}
