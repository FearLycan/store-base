<?php

declare(strict_types=1);

use yii\db\Migration;

class m260608_100005_create_sync_job_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%sync_job}}', [
            'id'            => $this->primaryKey(),
            'store_id'      => $this->integer()->null(),
            'product_id'    => $this->integer()->null(),
            'type'          => $this->string(32)->notNull(),
            'status'        => $this->string(16)->notNull()->defaultValue('pending'),
            'payload_json'  => $this->json()->null(),
            'attempts'      => $this->integer()->notNull()->defaultValue(0),
            'error_message' => $this->string(1000)->null(),
            'available_at'  => $this->integer()->notNull()->defaultValue(0),
            'processed_at'  => $this->integer()->null(),
            'created_at'    => $this->integer()->notNull(),
            'updated_at'    => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-sync_job-claim', '{{%sync_job}}', ['status', 'available_at', 'id']);
        $this->createIndex('idx-sync_job-type', '{{%sync_job}}', 'type');
        $this->createIndex('idx-sync_job-store', '{{%sync_job}}', 'store_id');
        $this->createIndex('idx-sync_job-product', '{{%sync_job}}', 'product_id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%sync_job}}');
    }
}
