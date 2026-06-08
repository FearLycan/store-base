<?php

declare(strict_types=1);

use yii\db\Migration;

class m260608_100004_create_review_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product_review}}', [
            'id'                 => $this->primaryKey(),
            'product_id'         => $this->integer()->notNull(),
            'external_review_id' => $this->string(64)->null(),
            'author_name'        => $this->string(255)->null(),
            'reviewer_country'   => $this->string(8)->null(),
            'rating_value'       => $this->decimal(4, 2)->null(),
            'rating_scale_max'   => $this->decimal(4, 2)->null(),
            'content'            => $this->text()->null(),
            'reviewed_at'        => $this->integer()->null(),
            'created_at'         => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-product_review-product_external', '{{%product_review}}', ['product_id', 'external_review_id'], true);
        $this->addForeignKey('fk-product_review-product', '{{%product_review}}', 'product_id', '{{%product}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%product_review_image}}', [
            'id'         => $this->primaryKey(),
            'review_id'  => $this->integer()->notNull(),
            'url'        => $this->string(1024)->notNull(),
            'position'   => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-product_review_image-review', '{{%product_review_image}}', 'review_id');
        $this->addForeignKey('fk-product_review_image-review', '{{%product_review_image}}', 'review_id', '{{%product_review}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%product_review_image}}');
        $this->dropTable('{{%product_review}}');
    }
}
