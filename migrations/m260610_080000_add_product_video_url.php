<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds product.video_url — the Affiliate API exposes `product_video_url` for many items.
 */
final class m260610_080000_add_product_video_url extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%product}}', 'video_url', $this->string(1024)->null()->after('main_image'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%product}}', 'video_url');
    }
}
