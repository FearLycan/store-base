<?php

namespace app\commands;

use common\components\aliexpress\AliExpressApiClient;
use common\components\aliexpress\AliExpressLinkResolver;
use common\components\aliexpress\AliExpressOfferMatcher;
use common\enums\SetOfferImportStatusEnum;
use common\models\Set;
use common\models\SetOffer;
use common\models\SetOfferImport;
use common\models\Store;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class SetOfferDiscoveryController extends Controller
{
    public bool $showProgress = false;
    public int $retryAfterDays = 0;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['showProgress', 'retryAfterDays']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['p' => 'showProgress', 'r' => 'retryAfterDays']);
    }

    public function actionQueueMatches(int $limit = 20, int $perSet = 3, float $minScore = 0.7, int $pageSize = 20): int
    {
        $normalizedLimit = max(1, $limit);
        $normalizedPerSet = max(1, $perSet);
        $normalizedMinScore = max(0.0, min(1.0, $minScore));
        $normalizedPageSize = min(50, max(1, $pageSize));
        $defaultRetryAfterDays = (int)(Yii::$app->params['setOfferDiscovery.retryAfterDays'] ?? 30);
        $retryAfterDays = $this->retryAfterDays > 0 ? $this->retryAfterDays : $defaultRetryAfterDays;
        $normalizedRetryAfterDays = max(1, $retryAfterDays);
        $retryCutoffAt = date('Y-m-d H:i:s', time() - ($normalizedRetryAfterDays * 86400));
        $this->logProgress("Starting discovery: limit={$normalizedLimit}, perSet={$normalizedPerSet}, minScore={$normalizedMinScore}, pageSize={$normalizedPageSize}, retryAfterDays={$normalizedRetryAfterDays}");

        $sets = Set::find()
            ->alias('set')
            ->leftJoin('{{%set_offer_import}} import', 'import.set_id = set.id')
            ->where(['not', ['number' => null]])
            ->andWhere(['not', ['name' => null]])
            ->andWhere(['import.id' => null])
            ->andWhere([
                'or',
                ['set.offer_discovery_checked_at' => null],
                ['<=', 'set.offer_discovery_checked_at', $retryCutoffAt],
            ])
            ->orderBy(['set.id' => SORT_ASC])
            ->limit($normalizedLimit);


        if (!$sets->exists()) {
            $this->stdout("No sets found.\n");

            return ExitCode::OK;
        }

        $apiClient = new AliExpressApiClient();
        $matcher = new AliExpressOfferMatcher();
        $linkResolver = new AliExpressLinkResolver();
        $store = Store::getOrCreate('ALIEXPRESS', 'AliExpress');

        $queuedTotal = 0;
        foreach ($sets->each(50) as $set) {
            $setNumber = trim((string)$set->number);
            $setName = trim((string)$set->name);
            $this->logProgress("Set #{$set->id} ({$setNumber} | {$setName}) - building queries");
            $queries = $matcher->buildQueries($set);
            if ($queries === []) {
                $this->logProgress("Set #{$set->id} skipped: no queries generated");
                $this->markSetDiscoveryChecked($set);
                continue;
            }

            $rankedCandidates = [];
            $hasSuccessfulQuery = false;
            foreach ($queries as $query) {
                $this->logProgress("Set #{$set->id}: searching query '{$query}'");
                try {
                    $items = $apiClient->fetchProductsByKeywords($query, 1, $normalizedPageSize);
                } catch (Throwable $exception) {
                    $this->stderr("Set #{$set->id}: query failed ({$query}): {$exception->getMessage()}\n");
                    sleep(1);
                    continue;
                }
                $hasSuccessfulQuery = true;
                $this->logProgress("Set #{$set->id}: query returned " . count($items) . " item(s)");

                foreach ($items as $item) {
                    $url = trim((string)($item['url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }

                    $itemId = $linkResolver->extractItemId($url);
                    if ($itemId === null) {
                        continue;
                    }

                    $score = $matcher->score($set, $item);
                    if ($score < $normalizedMinScore) {
                        continue;
                    }

                    $existingOffer = SetOffer::find()
                        ->where([
                            'set_id'      => $set->id,
                            'store_id'    => $store->id,
                            'external_id' => (string)$itemId,
                        ])
                        ->exists();
                    if ($existingOffer) {
                        continue;
                    }

                    $alreadyPending = SetOfferImport::find()
                        ->where([
                            'set_id'    => $set->id,
                            'input_url' => $url,
                        ])
                        ->exists();
                    if ($alreadyPending) {
                        continue;
                    }

                    $candidateKey = (string)$itemId;
                    $reviewCount = isset($item['review_count']) && is_numeric($item['review_count']) ? (int)$item['review_count'] : 0;
                    if (!isset($rankedCandidates[$candidateKey]) || $score > $rankedCandidates[$candidateKey]['score']) {
                        $rankedCandidates[$candidateKey] = [
                            'url'          => $url,
                            'score'        => $score,
                            'review_count' => $reviewCount,
                        ];
                    }
                }

                sleep(1);
            }

            if ($rankedCandidates === []) {
                if ($hasSuccessfulQuery) {
                    $this->markSetDiscoveryChecked($set);
                }
                $this->logProgress("Set #{$set->id}: no candidates matched threshold");
                continue;
            }

            usort($rankedCandidates, static function (array $left, array $right): int {
                if ($left['score'] === $right['score']) {
                    return $right['review_count'] <=> $left['review_count'];
                }

                return $right['score'] <=> $left['score'];
            });

            $queuedForSet = 0;
            foreach ($rankedCandidates as $candidate) {
                if ($queuedForSet >= $normalizedPerSet) {
                    break;
                }

                $importTask = new SetOfferImport();
                $importTask->set_id = $set->id;
                $importTask->input_url = (string)$candidate['url'];
                $importTask->status = SetOfferImportStatusEnum::AWAITING_REVIEW->value;
                if (!$importTask->save()) {
                    $errorSummary = implode('; ', $importTask->getFirstErrors());
                    $this->stderr("Set #{$set->id}: failed to queue URL: {$errorSummary}\n");
                    continue;
                }

                $queuedForSet++;
                $queuedTotal++;
            }

            if ($queuedForSet > 0) {
                $this->stdout("Set #{$set->id}: queued {$queuedForSet} offer(s).\n");
            } else {
                $this->markSetDiscoveryChecked($set);
                $this->logProgress("Set #{$set->id}: candidates found but nothing queued");
            }
        }

        $this->stdout("Done. Total queued offers: {$queuedTotal}\n");

        return ExitCode::OK;
    }

    private function logProgress(string $message): void
    {
        if (!$this->showProgress) {
            return;
        }

        $this->stdout("[progress] {$message}\n");
    }

    private function markSetDiscoveryChecked(Set $set): void
    {
        $checkedAt = date('Y-m-d H:i:s');
        if ($set->updateAttributes(['offer_discovery_checked_at' => $checkedAt]) === false) {
            $this->stderr("Set #{$set->id}: failed to update offer_discovery_checked_at.\n");
        }
    }
}
