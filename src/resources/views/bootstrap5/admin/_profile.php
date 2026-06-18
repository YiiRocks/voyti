<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Email;
use Yiisoft\Form\Field\ErrorSummary;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Form\Field\Textarea;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\SettingsForm $model
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-admin-update-profile">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.admin.update_profile_title') ?></h2>
    <form method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('profile[name]')->value($model->name)->label($translator->translate('voyti.view.name_label')) ?>
        <?= Textarea::widget()->name('profile[bio]')->value($model->bio)->rows(3)->label($translator->translate('voyti.view.bio_label')) ?>
        <?= Email::widget()->name('profile[publicEmail]')->value($model->publicEmail)->label($translator->translate('voyti.view.public_email_label')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.update_button')) ?>
    </form>
</div>
