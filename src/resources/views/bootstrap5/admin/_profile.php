<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Settings\ProfileForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var ProfileForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.admin.update_profile_title', category: 'voyti')); ?>
<div class="voyti-admin-update-profile">
    <h1><?= $translator->translate('voyti.view.admin.update_profile_title', category: 'voyti') ?></h1>
<?php
$form = Html::form(
    $url->generate('voyti/admin-update-profile', ['id' => $user->getId()]),
    'post',
    ['novalidate' => true]
);
?>
<?= $form->begin() ?>
    <?= Field::errorSummary(null)->errors($errors) ?>
    <?= Field::text($model, 'name') ?>
    <?= Field::textarea($model, 'bio')->rows(3) ?>
    <?= Field::email($model, 'publicEmail') ?>
    <?= Field::buttonGroup()
        ->buttons(
            Button::submit($translator->translate('voyti.view.update_button', category: 'voyti'))
        )
?>
    <?= $form->end() ?>
</div>
