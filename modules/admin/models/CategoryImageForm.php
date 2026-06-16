<?php

declare(strict_types=1);

namespace app\modules\admin\models;

use app\models\Category;
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Admin form for a category's storefront cover image. Supports three ways to
 * set it — upload a file, paste a URL, or clear it — and persists the result
 * to {@see Category::$image_url}. Uploaded files land under web/uploads/categories
 * and are stored as a root-relative URL; a previously uploaded file is removed
 * when replaced or cleared. When image_url ends up empty the storefront falls
 * back to the category's best-selling product photo.
 */
final class CategoryImageForm extends Model
{
    public const UPLOAD_DIR = '@webroot/uploads/categories';
    public const UPLOAD_URL = '/uploads/categories';

    public ?UploadedFile $file = null;
    public ?string $imageUrl = null;
    public bool $remove = false;

    public function rules(): array
    {
        return [
            [['imageUrl'], 'trim'],
            [['imageUrl'], 'string', 'max' => 1024],
            [['remove'], 'boolean'],
            [['file'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, webp, gif', 'maxSize' => 4 * 1024 * 1024],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'file'     => 'Upload an image',
            'imageUrl' => '…or paste an image URL',
            'remove'   => 'Remove the current image',
        ];
    }

    /**
     * Validate, resolve the chosen source (remove → upload → URL → unchanged),
     * and save the category. Returns false with model errors on failure.
     */
    public function apply(Category $category): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->remove) {
            $this->deleteLocal($category->image_url);
            $category->image_url = null;
        } elseif ($this->file !== null) {
            $saved = $this->store($category);
            if ($saved === null) {
                return false;
            }
            $this->deleteLocal($category->image_url);
            $category->image_url = $saved;
        } elseif (($url = trim((string)$this->imageUrl)) !== '') {
            $category->image_url = $url;
        }

        return $category->save(false);
    }

    /** Persist the uploaded file, returning its root-relative URL or null on error. */
    private function store(Category $category): ?string
    {
        $dir = Yii::getAlias(self::UPLOAD_DIR);
        try {
            FileHelper::createDirectory($dir, 0775);
        } catch (\Throwable $e) {
            $this->addError('file', 'Upload directory is not writable.');
            return null;
        }

        $ext  = strtolower((string)($this->file->extension ?: 'jpg'));
        $name = sprintf('category-%d-%s.%s', $category->id, Yii::$app->security->generateRandomString(8), $ext);

        if (!$this->file->saveAs($dir . '/' . $name)) {
            $this->addError('file', 'Could not save the uploaded file.');
            return null;
        }

        return self::UPLOAD_URL . '/' . $name;
    }

    /** Delete a previously uploaded file (only our own /uploads paths, never external URLs). */
    private function deleteLocal(?string $url): void
    {
        if ($url === null || !str_starts_with($url, self::UPLOAD_URL . '/')) {
            return;
        }
        $path = Yii::getAlias('@webroot') . $url;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
