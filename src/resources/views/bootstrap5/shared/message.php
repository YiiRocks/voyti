<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var string $title
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <h2 class="card-title h4 mb-4"><?= Html::encode($title) ?></h2>
        <a href="/" class="btn btn-primary"><?= $translator->translate('voyti.view.go_home', category: 'voyti') ?></a>
    </div>
</div>
