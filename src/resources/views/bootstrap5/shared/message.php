<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $title
 * @var TranslatorInterface $translator
 */
?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <h1><?= Html::encode($title) ?></h1>
        <a href="/" class="btn btn-primary"><?= $translator->translate('voyti.view.go_home', category: 'voyti') ?></a>
    </div>
</div>
