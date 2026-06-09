<?php

declare(strict_types=1);

use yii\db\Migration;

class m260609_194803_storefront_product_click_and_indexes extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product_click}}', [
            'id'         => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'referrer'   => $this->string(1024)->null(),
            'ua_hash'    => $this->string(64)->null(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-product_click-product', '{{%product_click}}', 'product_id');
        $this->createIndex('idx-product_click-created', '{{%product_click}}', 'created_at');
        $this->addForeignKey('fk-product_click-product', '{{%product_click}}', 'product_id', '{{%product}}', 'id', 'CASCADE', 'CASCADE');

        $this->addColumn('{{%product}}', 'click_count', $this->integer()->notNull()->defaultValue(0));

        $this->createIndex('idx-product-status_category', '{{%product}}', ['status', 'category_id']);
        $this->createIndex('idx-product-status_orders', '{{%product}}', ['status', 'orders_count']);
        $this->createIndex('idx-product-status_price', '{{%product}}', ['status', 'price']);

        $product = $this->db->schema->getRawTableName('{{%product}}');
        $category = $this->db->schema->getRawTableName('{{%category}}');
        // Prefix length 191 keeps the key within InnoDB limits (slug is varchar(512)).
        // product.slug unique = public URL key; existing NULLs don't collide. category.slug = plain
        // prefix index (app-level uniqueness via SluggableBehavior).
        $this->execute("ALTER TABLE `{$product}` ADD UNIQUE INDEX `ux_product_slug` (`slug`(191))");
        $this->execute("ALTER TABLE `{$category}` ADD INDEX `idx_category_slug` (`slug`(191))");
        $this->execute("ALTER TABLE `{$product}` ADD FULLTEXT INDEX `ftx_product_title` (title)");
    }

    public function safeDown(): void
    {
        $product = $this->db->schema->getRawTableName('{{%product}}');
        $category = $this->db->schema->getRawTableName('{{%category}}');
        $this->execute("ALTER TABLE `{$product}` DROP INDEX `ftx_product_title`");
        $this->execute("ALTER TABLE `{$category}` DROP INDEX `idx_category_slug`");
        $this->execute("ALTER TABLE `{$product}` DROP INDEX `ux_product_slug`");
        $this->dropIndex('idx-product-status_price', '{{%product}}');
        $this->dropIndex('idx-product-status_orders', '{{%product}}');
        $this->dropIndex('idx-product-status_category', '{{%product}}');
        $this->dropColumn('{{%product}}', 'click_count');
        $this->dropForeignKey('fk-product_click-product', '{{%product_click}}');
        $this->dropTable('{{%product_click}}');
    }
}
