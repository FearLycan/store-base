<?php

declare(strict_types=1);

use yii\db\Migration;

class m260608_100002_create_product_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product}}', [
            'id'                    => $this->primaryKey(),
            'store_id'              => $this->integer()->notNull(),
            'external_id'           => $this->string(64)->notNull(),
            'title'                 => $this->string(512)->null(),
            'slug'                  => $this->string(512)->null(),
            'description'           => $this->text()->null(),
            'main_image'            => $this->string(1024)->null(),
            'product_url'           => $this->string(1024)->null(),
            'affiliate_url'         => $this->string(1024)->null(),
            'currency_code'         => $this->string(8)->notNull()->defaultValue('USD'),
            'price'                 => $this->integer()->null(),
            'original_price'        => $this->integer()->null(),
            'rating_value'          => $this->decimal(4, 2)->null(),
            'rating_scale_max'      => $this->decimal(4, 2)->null(),
            'review_count'          => $this->integer()->notNull()->defaultValue(0),
            'orders_count'          => $this->integer()->notNull()->defaultValue(0),
            'availability'          => $this->string(64)->null(),
            'status'                => $this->string(16)->notNull()->defaultValue('active'),
            'source'                => $this->string(32)->notNull()->defaultValue('aliexpress'),
            'review_impressions'    => $this->json()->null(),
            'first_imported_at'     => $this->integer()->null(),
            'last_detail_synced_at' => $this->integer()->null(),
            'last_price_synced_at'  => $this->integer()->null(),
            'last_review_synced_at' => $this->integer()->null(),
            'created_at'            => $this->integer()->notNull(),
            'updated_at'            => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-product-store_external', '{{%product}}', ['store_id', 'external_id'], true);
        $this->createIndex('idx-product-status', '{{%product}}', 'status');
        $this->createIndex('idx-product-last_price', '{{%product}}', 'last_price_synced_at');
        $this->createIndex('idx-product-last_review', '{{%product}}', 'last_review_synced_at');
        $this->addForeignKey('fk-product-store', '{{%product}}', 'store_id', '{{%store}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%product}}');
    }
}
