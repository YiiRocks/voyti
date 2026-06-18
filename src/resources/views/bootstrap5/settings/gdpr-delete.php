<?php

declare(strict_types=1);
use Yiisoft\FormModel\Field;

use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Settings\GdprDeleteForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<div class="voyti-gdpr-delete">
    <h1><?= $translator->translate('voyti.view.gdpr.delete_title', category: 'voyti') ?></h1>
    <p class="alert alert-warning"><?= $translator->translate('voyti.view.gdpr.delete_warning', category: 'voyti') ?></p>
    <form method="post" novalidate>
        <?= Field::checkbox($model, 'consent')->inputValue('1') ?>
        <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.gdpr.delete_button', category: 'voyti'))
            )
?>
    </form>
</div>
