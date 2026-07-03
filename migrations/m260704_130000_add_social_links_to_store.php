<?php

declare(strict_types=1);

use yii\db\Migration;

class m260704_130000_add_social_links_to_store extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%store}}', 'website_url', $this->string(1024)->null()->after('url'));
        $this->addColumn('{{%store}}', 'instagram_url', $this->string(1024)->null()->after('website_url'));
        $this->addColumn('{{%store}}', 'facebook_url', $this->string(1024)->null()->after('instagram_url'));
        $this->addColumn('{{%store}}', 'tiktok_url', $this->string(1024)->null()->after('facebook_url'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%store}}', 'tiktok_url');
        $this->dropColumn('{{%store}}', 'facebook_url');
        $this->dropColumn('{{%store}}', 'instagram_url');
        $this->dropColumn('{{%store}}', 'website_url');
    }
}
