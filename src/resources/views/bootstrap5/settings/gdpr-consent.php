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
<div class="voyti-gdpr-consent">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.gdpr.consent_title') ?></h2>
    <form method="post" novalidate>
        <?= Checkbox::widget()->name('gdprDelete[consent]')->inputValue('1')->label($translator->translate('voyti.view.gdpr.consent_label')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.save_button')) ?>
    </form>
</div>
