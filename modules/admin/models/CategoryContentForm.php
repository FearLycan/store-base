<?php

declare(strict_types=1);

namespace app\modules\admin\models;

use app\models\Category;
use yii\base\Model;

/**
 * Admin form for a category's storefront content: an intro paragraph and a
 * short FAQ. FAQ rows are edited as FAQ_ROWS fixed question/answer pairs and
 * persisted to {@see Category::$faq_json} as a list of {q,a}; the intro is
 * stored raw in {@see Category::$intro_html} and sanitized at render.
 */
final class CategoryContentForm extends Model
{
    public const FAQ_ROWS = 6;

    public ?string $introHtml = null;
    /** @var array<int, string> */
    public array $faqQ = [];
    /** @var array<int, string> */
    public array $faqA = [];

    public function rules(): array
    {
        return [
            [['introHtml'], 'string'],
            [['faqQ', 'faqA'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return ['introHtml' => 'Intro (shown above the product grid)'];
    }

    /** Seed the form from a category's stored content (for the edit screen). */
    public function loadFrom(Category $category): void
    {
        $this->introHtml = $category->intro_html;
        $faq = is_array($category->faq_json) ? $category->faq_json : [];
        foreach ($faq as $row) {
            $this->faqQ[] = (string) ($row['q'] ?? '');
            $this->faqA[] = (string) ($row['a'] ?? '');
        }
    }

    /** Validate, zip non-empty Q/A pairs into faq_json, store the intro raw, save. */
    public function apply(Category $category): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $intro = trim((string) $this->introHtml);
        $category->intro_html = $intro !== '' ? $intro : null;

        $pairs = [];
        $count = max(count($this->faqQ), count($this->faqA));
        for ($i = 0; $i < $count; $i++) {
            $q = trim((string) ($this->faqQ[$i] ?? ''));
            $a = trim((string) ($this->faqA[$i] ?? ''));
            if ($q !== '' && $a !== '') {
                $pairs[] = ['q' => $q, 'a' => $a];
            }
        }
        $category->faq_json = $pairs !== [] ? $pairs : null;

        return $category->save(false);
    }
}
