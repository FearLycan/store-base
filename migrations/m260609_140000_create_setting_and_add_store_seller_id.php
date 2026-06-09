<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds a generic key/value `setting` table (used to store the admin-pasted AliExpress
 * session cookie incl. x5sec) and a resolved `seller_id` (ownerMemberId) column on `store`,
 * needed by the shoprenderview store-listing endpoint.
 */
class m260609_140000_create_setting_and_add_store_seller_id extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%setting}}', [
            'name'       => $this->string(128)->notNull(),
            'value'      => $this->text()->null(),
            'updated_at' => $this->integer()->notNull(),
            'PRIMARY KEY(name)',
        ]);

        $this->addColumn('{{%store}}', 'seller_id', $this->string(64)->null()->after('external_store_id'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%store}}', 'seller_id');
        $this->dropTable('{{%setting}}');
    }
}
