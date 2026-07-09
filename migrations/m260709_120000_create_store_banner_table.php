<?php

declare(strict_types=1);

use yii\db\Migration;

class m260709_120000_create_store_banner_table extends Migration
{
    public function safeUp(): void
    {
        // Admin-managed hero banners for the store page slider. image_url is a
        // root-relative /uploads/banners/… path for uploads, or an external URL.
        $this->createTable('{{%store_banner}}', [
            'id'          => $this->primaryKey(),
            'store_id'    => $this->integer()->notNull(),
            'image_url'   => $this->string(1024)->notNull(),
            'link_url'    => $this->string(1024)->null(),
            'headline'    => $this->string(255)->null(),
            'subheadline' => $this->string(255)->null(),
            'sort_order'  => $this->integer()->notNull()->defaultValue(0),
            'status'      => $this->string(16)->notNull()->defaultValue('active'),
            'created_at'  => $this->integer()->notNull(),
            'updated_at'  => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-store_banner-store_id', '{{%store_banner}}', 'store_id');
        $this->addForeignKey('fk-store_banner-store_id', '{{%store_banner}}', 'store_id', '{{%store}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%store_banner}}');
    }
}
