<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Real AliExpress review aggregates so the reviews section shows the FULL-corpus
 * numbers (total, star distribution, customer photos) instead of the tiny stored
 * sample — otherwise narrowing to a filter (e.g. "with photos") could show MORE
 * than "all", and the photo strip under-represented what the filter returns.
 */
class m260705_130000_add_review_stats extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%product}}', 'review_total', $this->integer()->null());
        $this->addColumn('{{%product}}', 'review_rating_dist', $this->json()->null());
        $this->addColumn('{{%product}}', 'review_photos', $this->json()->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%product}}', 'review_photos');
        $this->dropColumn('{{%product}}', 'review_rating_dist');
        $this->dropColumn('{{%product}}', 'review_total');
    }
}
