<?php

declare(strict_types=1);

use yii\db\Migration;

class m260705_120000_add_review_image_count extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%product}}', 'review_image_count', $this->integer()->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%product}}', 'review_image_count');
    }
}
