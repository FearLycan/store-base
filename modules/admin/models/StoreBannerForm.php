<?php

declare(strict_types=1);

namespace app\modules\admin\models;

use app\models\Store;
use app\models\StoreBanner;
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Admin form for adding a store hero banner: upload a file or paste an image
 * URL, plus optional overlay copy and a CTA link. Uploads land under
 * web/uploads/banners and are stored as root-relative URLs — the same pattern
 * as {@see CategoryImageForm}.
 */
final class StoreBannerForm extends Model
{
    public const UPLOAD_DIR = '@webroot/uploads/banners';
    public const UPLOAD_URL = '/uploads/banners';

    /**
     * Resolved from $_FILES via UploadedFile::getInstance() in the controller.
     * Left untyped on purpose: ActiveForm's fileInput() also posts an empty
     * string for this field name, which load() would otherwise reject.
     *
     * @var UploadedFile|null
     */
    public $file;
    public ?string $imageUrl = null;
    public ?string $linkUrl = null;
    public ?string $headline = null;
    public ?string $subheadline = null;
    public $sortOrder = 0;

    public function rules(): array
    {
        return [
            [['imageUrl', 'linkUrl', 'headline', 'subheadline'], 'trim'],
            [['imageUrl', 'linkUrl'], 'string', 'max' => 1024],
            [['headline', 'subheadline'], 'string', 'max' => 255],
            [['sortOrder'], 'integer'],
            [['sortOrder'], 'default', 'value' => 0],
            [['file'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, webp, gif', 'maxSize' => 4 * 1024 * 1024],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'file'        => 'Upload an image',
            'imageUrl'    => '…or paste an image URL',
            'linkUrl'     => 'Link URL (optional — where the banner leads)',
            'headline'    => 'Headline (optional overlay title)',
            'subheadline' => 'Subheadline (optional overlay text)',
            'sortOrder'   => 'Sort order',
        ];
    }

    /**
     * Validate, resolve the image source (upload wins over URL), and create the
     * banner. Returns the new banner, or null with model errors on failure.
     */
    public function apply(Store $store): ?StoreBanner
    {
        if (!$this->validate()) {
            return null;
        }

        $url = trim((string)$this->imageUrl);
        if ($this->file !== null) {
            $saved = $this->store($store);
            if ($saved === null) {
                return null;
            }
            $url = $saved;
        }
        if ($url === '') {
            $this->addError('file', 'Upload a file or paste an image URL.');
            return null;
        }

        $banner = new StoreBanner([
            'store_id'    => $store->id,
            'image_url'   => $url,
            'link_url'    => trim((string)$this->linkUrl) !== '' ? trim((string)$this->linkUrl) : null,
            'headline'    => trim((string)$this->headline) !== '' ? trim((string)$this->headline) : null,
            'subheadline' => trim((string)$this->subheadline) !== '' ? trim((string)$this->subheadline) : null,
            'sort_order'  => (int)$this->sortOrder,
        ]);
        if (!$banner->save()) {
            $this->addErrors($banner->getErrors());
            return null;
        }

        return $banner;
    }

    /** Persist the uploaded file, returning its root-relative URL or null on error. */
    private function store(Store $storeModel): ?string
    {
        $dir = Yii::getAlias(self::UPLOAD_DIR);
        try {
            FileHelper::createDirectory($dir, 0775);
        } catch (\Throwable $e) {
            $this->addError('file', 'Upload directory is not writable.');
            return null;
        }

        $ext  = strtolower((string)($this->file->extension ?: 'jpg'));
        $name = sprintf('banner-%d-%s.%s', $storeModel->id, Yii::$app->security->generateRandomString(8), $ext);

        if (!$this->file->saveAs($dir . '/' . $name)) {
            $this->addError('file', 'Could not save the uploaded file.');
            return null;
        }

        return self::UPLOAD_URL . '/' . $name;
    }

    /** Delete a banner row and its locally uploaded file (only own /uploads paths, never external URLs). */
    public static function deleteBanner(StoreBanner $banner): void
    {
        $url = (string)$banner->image_url;
        $banner->delete();
        if (str_starts_with($url, self::UPLOAD_URL . '/')) {
            $path = Yii::getAlias('@webroot') . $url;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
