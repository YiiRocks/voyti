<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Checkbox;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\GdprDeleteForm $model
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-gdpr-delete">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.gdpr.delete_title') ?></h2>
    <p class="alert alert-warning"><?= $translator->translate('voyti.view.gdpr.delete_warning') ?></p>
    <form method="post" novalidate>
        <?= Checkbox::widget()->name('gdprDelete[consent]')->inputValue('1')->label($translator->translate('voyti.view.gdpr.delete_confirm_label')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.gdpr.delete_button')) ?>
    </form>
</div>
