<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds category.image_url — an admin-set cover image for the storefront's
 * "Shop by category" tiles. When empty, the front falls back to the
 * best-selling product's photo (see CatalogController::categoryCovers).
 */
final class m260616_120000_add_category_image_url extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%category}}', 'image_url', $this->string(1024)->null()->after('slug'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%category}}', 'image_url');
    }
}
