<?php

declare(strict_types=1);

use yii\db\Migration;

class m260608_100006_create_category_and_link_product extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%category}}', [
            'id'                   => $this->primaryKey(),
            'external_category_id' => $this->string(64)->notNull(),
            'parent_id'            => $this->integer()->null(),
            'name'                 => $this->string(255)->notNull(),
            'slug'                 => $this->string(255)->null(),
            'level'                => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'created_at'           => $this->integer()->notNull(),
            'updated_at'           => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-category-external', '{{%category}}', 'external_category_id', true);
        $this->createIndex('idx-category-parent', '{{%category}}', 'parent_id');
        $this->addForeignKey('fk-category-parent', '{{%category}}', 'parent_id', '{{%category}}', 'id', 'SET NULL', 'CASCADE');

        $this->addColumn('{{%product}}', 'category_id', $this->integer()->null()->after('store_id'));
        $this->createIndex('idx-product-category', '{{%product}}', 'category_id');
        $this->addForeignKey('fk-product-category', '{{%product}}', 'category_id', '{{%category}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-product-category', '{{%product}}');
        $this->dropIndex('idx-product-category', '{{%product}}');
        $this->dropColumn('{{%product}}', 'category_id');
        $this->dropTable('{{%category}}');
    }
}
