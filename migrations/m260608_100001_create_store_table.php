<?php

declare(strict_types=1);

use yii\db\Migration;

class m260608_100001_create_store_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%store}}', [
            'id'                => $this->primaryKey(),
            'external_store_id' => $this->string(64)->notNull(),
            'name'              => $this->string(255)->notNull(),
            'url'               => $this->string(1024)->notNull(),
            'seller_admin_seq'  => $this->string(64)->null(),
            'status'            => $this->string(16)->notNull()->defaultValue('active'),
            'last_discovery_at' => $this->integer()->null(),
            'last_full_sync_at' => $this->integer()->null(),
            'product_count'     => $this->integer()->notNull()->defaultValue(0),
            'created_at'        => $this->integer()->notNull(),
            'updated_at'        => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-store-external_store_id', '{{%store}}', 'external_store_id', true);
        $this->createIndex('idx-store-status', '{{%store}}', 'status');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%store}}');
    }
}
