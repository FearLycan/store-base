<?php

declare(strict_types=1);

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\View;

final class ShopAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = ['css/app.css'];
    public $js = ['js/alpine.min.js'];
    public $jsOptions = ['defer' => true, 'position' => View::POS_END];
    public $depends = [\yii\web\YiiAsset::class];
}
