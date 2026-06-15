<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds product.display_title — the human-friendly, LLM-rewritten product name shown on the
 * storefront. The raw `title` (keyword-stuffed name from AliExpress) is kept as the source of
 * truth and for full-text search recall; `display_title` is what visitors actually see.
 */
final class m260615_000000_add_product_display_title extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%product}}', 'display_title', $this->string(512)->null()->after('title'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%product}}', 'display_title');
    }
}
