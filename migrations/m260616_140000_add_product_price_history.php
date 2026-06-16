<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Price-drop signal + full price history.
 * - product.previous_price / price_changed_at: denormalized fast-path for the
 *   "price dropped" badge on listing cards.
 * - product_price_history: one row per price change (and first import), so price
 *   charts can be built later without per-card subqueries.
 */
final class m260616_140000_add_product_price_history extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%product}}', 'previous_price', $this->integer()->null()->after('original_price'));
        $this->addColumn('{{%product}}', 'price_changed_at', $this->integer()->null()->after('previous_price'));

        $this->createTable('{{%product_price_history}}', [
            'id'             => $this->primaryKey(),
            'product_id'     => $this->integer()->notNull(),
            'price'          => $this->integer()->null(),
            'original_price' => $this->integer()->null(),
            'currency_code'  => $this->string(8)->null(),
            'recorded_at'    => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-pph-product-recorded', '{{%product_price_history}}', ['product_id', 'recorded_at']);
        $this->addForeignKey('fk-pph-product', '{{%product_price_history}}', 'product_id', '{{%product}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%product_price_history}}');
        $this->dropColumn('{{%product}}', 'price_changed_at');
        $this->dropColumn('{{%product}}', 'previous_price');
    }
}
