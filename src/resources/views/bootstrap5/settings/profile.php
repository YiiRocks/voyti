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
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var YiiRocks\Voyti\Entity\User $user
 * @var YiiRocks\Voyti\Entity\Profile $profile
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-settings">
    <?php include dirname(__DIR__) . '/shared/_menu.php'; ?>
    <h2 class="mb-4"><?= $translator->translate('voyti.view.settings.title', category: 'voyti') ?></h2>
    <form method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('settings[name]')->value($model->name)->label($translator->translate('voyti.view.name_label', category: 'voyti')) ?>
        <?= Email::widget()->name('settings[publicEmail]')->value($model->publicEmail)->label($translator->translate('voyti.view.public_email_label', category: 'voyti')) ?>
        <?= Textarea::widget()->name('settings[bio]')->value($model->bio)->rows(3)->label($translator->translate('voyti.view.bio_label', category: 'voyti')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.save_button', category: 'voyti')) ?>
    </form>
</div>
