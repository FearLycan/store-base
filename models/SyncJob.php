<?php

declare(strict_types=1);

namespace app\models;

use app\enums\SyncJobStatusEnum;
use app\enums\SyncJobTypeEnum;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property int|null $store_id
 * @property int|null $product_id
 * @property string $type
 * @property string $status
 * @property array|null $payload_json
 * @property int $attempts
 * @property string|null $error_message
 * @property int $available_at
 * @property int|null $processed_at
 * @property int $created_at
 * @property int $updated_at
 */
class SyncJob extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%sync_job}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['type'], 'required'],
            [['store_id', 'product_id', 'attempts', 'available_at', 'processed_at'], 'integer'],
            [['payload_json'], 'safe'],
            [['type', 'status'], 'string', 'max' => 32],
            [['error_message'], 'string', 'max' => 1000],
        ];
    }

    public static function enqueue(SyncJobTypeEnum $type, ?int $storeId, ?int $productId, array $payload = []): self
    {
        $job = new self();
        $job->type = $type->value;
        $job->status = SyncJobStatusEnum::PENDING->value;
        $job->store_id = $storeId;
        $job->product_id = $productId;
        $job->payload_json = $payload;
        $job->available_at = time();
        $job->save(false);

        return $job;
    }

    /**
     * Atomically claim a single pending, due job. Returns the claimed job or null.
     */
    public static function claimNext(): ?self
    {
        $candidate = self::find()
            ->where(['status' => SyncJobStatusEnum::PENDING->value])
            ->andWhere(['<=', 'available_at', time()])
            ->orderBy(self::priorityExpression())
            ->addOrderBy(['id' => SORT_ASC])
            ->one();
        if ($candidate === null) {
            return null;
        }

        $claimed = self::updateAll(
            ['status' => SyncJobStatusEnum::PROCESSING->value, 'updated_at' => time()],
            ['id' => $candidate->id, 'status' => SyncJobStatusEnum::PENDING->value]
        );

        if ($claimed !== 1) {
            return null; // lost the race; caller loops again
        }

        $candidate->refresh();

        return $candidate;
    }

    /**
     * SQL `CASE` mapping each job type to its {@see SyncJobTypeEnum::queuePriority()} weight, so the
     * queue can be ordered by priority then id. Built from the enum (values are fixed, injection-safe).
     */
    private static function priorityExpression(): Expression
    {
        $cases = '';
        foreach (SyncJobTypeEnum::cases() as $case) {
            $cases .= sprintf(" WHEN '%s' THEN %d", $case->value, $case->queuePriority());
        }

        return new Expression('CASE [[type]]' . $cases . ' ELSE 99 END');
    }

    public function markDone(): void
    {
        $this->status = SyncJobStatusEnum::DONE->value;
        $this->error_message = null;
        $this->processed_at = time();
        $this->save(false);
    }

    /**
     * Terminal skip for a permanent, non-retryable condition (e.g. the product is not in the affiliate
     * program). Records the reason so it stays visible in the admin queue, but — unlike {@see markFailed} —
     * does not consume the retry budget or bounce the job back to `pending`.
     */
    public function markSkipped(string $reason): void
    {
        $this->status = SyncJobStatusEnum::SKIPPED->value;
        $this->error_message = mb_substr(trim($reason), 0, 1000);
        $this->processed_at = time();
        $this->save(false);
    }

    public function markFailed(string $error, int $maxAttempts, int $backoffSeconds = 3600): void
    {
        $this->attempts++;
        $this->error_message = mb_substr(trim($error), 0, 1000);
        if ($this->attempts >= $maxAttempts) {
            $this->status = SyncJobStatusEnum::FAILED->value;
        } else {
            $this->status = SyncJobStatusEnum::PENDING->value;          // retry
            $this->available_at = time() + ($backoffSeconds * $this->attempts);
        }
        $this->save(false);
    }
}
