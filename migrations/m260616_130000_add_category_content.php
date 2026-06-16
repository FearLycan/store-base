<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds admin-editable storefront content to categories:
 * - intro_html: an intro paragraph shown above the product grid.
 * - faq_json: a list of {q,a} pairs shown as an FAQ accordion (+ FAQPage JSON-LD).
 * Both nullable; empty means the storefront renders nothing for that section.
 */
final class m260616_130000_add_category_content extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%category}}', 'intro_html', $this->text()->null()->after('image_url'));
        $this->addColumn('{{%category}}', 'faq_json', $this->json()->null()->after('intro_html'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%category}}', 'faq_json');
        $this->dropColumn('{{%category}}', 'intro_html');
    }
}
