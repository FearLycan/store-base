<?php

declare(strict_types=1);

use yii\db\Migration;
use yii\helpers\Inflector;

class m260704_120000_add_image_and_slug_to_store extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%store}}', 'image_url', $this->string(1024)->null()->after('name'));
        $this->addColumn('{{%store}}', 'slug', $this->string(255)->null()->after('name'));

        // Backfill a unique slug for every existing store so the public /store/<slug>
        // route resolves. Derived from the display name, deduped with -2, -3, … suffixes.
        $used = [];
        $rows = (new \yii\db\Query())->select(['id', 'name'])->from('{{%store}}')->all($this->db);
        foreach ($rows as $row) {
            $base = Inflector::slug((string) $row['name']);
            if ($base === '') {
                $base = 'store-' . $row['id'];
            }
            $slug = $base;
            $i = 2;
            while (isset($used[$slug])) {
                $slug = $base . '-' . $i++;
            }
            $used[$slug] = true;
            $this->update('{{%store}}', ['slug' => $slug], ['id' => $row['id']]);
        }

        $this->createIndex('idx-store-slug', '{{%store}}', 'slug', true);
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx-store-slug', '{{%store}}');
        $this->dropColumn('{{%store}}', 'slug');
        $this->dropColumn('{{%store}}', 'image_url');
    }
}
