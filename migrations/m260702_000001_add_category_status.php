<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds category.status (active|inactive). An inactive category — and its whole
 * subtree — is hidden from the storefront: it drops out of navigation, its
 * /catalog/category page 404s, and its products vanish from every listing
 * (see Category::hiddenIds + CatalogQuery::active). Admins flip it from the
 * category list over AJAX. Defaults to active so existing rows stay visible.
 */
final class m260702_000001_add_category_status extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%category}}', 'status', $this->string(16)->notNull()->defaultValue('active')->after('image_url'));
        $this->createIndex('idx-category-status', '{{%category}}', 'status');
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx-category-status', '{{%category}}');
        $this->dropColumn('{{%category}}', 'status');
    }
}
