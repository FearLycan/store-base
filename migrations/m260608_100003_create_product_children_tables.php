<?php

declare(strict_types=1);

use yii\db\Migration;

class m260608_100003_create_product_children_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product_image}}', [
            'id'         => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'url'        => $this->string(1024)->notNull(),
            'position'   => $this->integer()->notNull()->defaultValue(0),
            'is_main'    => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-product_image-product', '{{%product_image}}', 'product_id');
        $this->addForeignKey('fk-product_image-product', '{{%product_image}}', 'product_id', '{{%product}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%product_variant}}', [
            'id'             => $this->primaryKey(),
            'product_id'     => $this->integer()->notNull(),
            'external_sku_id'=> $this->string(64)->null(),
            'name'           => $this->string(512)->null(),
            'options_json'   => $this->json()->null(),
            'price'          => $this->integer()->null(),
            'original_price' => $this->integer()->null(),
            'stock'          => $this->integer()->null(),
            'image'          => $this->string(1024)->null(),
            'currency_code'  => $this->string(8)->null(),
            'created_at'     => $this->integer()->notNull(),
            'updated_at'     => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-product_variant-product', '{{%product_variant}}', 'product_id');
        $this->addForeignKey('fk-product_variant-product', '{{%product_variant}}', 'product_id', '{{%product}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%product_attribute}}', [
            'id'         => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'name'       => $this->string(255)->notNull(),
            'value'      => $this->string(1024)->null(),
            'position'   => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-product_attribute-product', '{{%product_attribute}}', 'product_id');
        $this->addForeignKey('fk-product_attribute-product', '{{%product_attribute}}', 'product_id', '{{%product}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%product_attribute}}');
        $this->dropTable('{{%product_variant}}');
        $this->dropTable('{{%product_image}}');
    }
}
