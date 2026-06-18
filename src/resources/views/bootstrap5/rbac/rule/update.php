<?php

declare(strict_types=1);
use YiiRocks\Voyti\Form\Rbac\RuleForm;

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var RuleForm $model
 * @var array $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<?php $this->setTitle($translator->translate('voyti.view.rule.update_title', category: 'voyti')); ?>
<div class="voyti-rbac-update">
    <h1><?= $translator->translate('voyti.view.rule.update_title', category: 'voyti') ?></h1>
<?php
$form = Html::form(
    $url->generate('voyti/rules-update', ['name' => $model->itemName]),
    'post',
    ['novalidate' => true]
);
?>
<?= $form->begin() ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $field => $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $error): ?>
                    <div><?= Html::encode($error) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= Field::text($model, 'name') ?>
    <?= Field::text($model, 'class') ?>
    <?= Field::buttonGroup()
        ->buttons(
            Button::submit($translator->translate('voyti.view.update_button', category: 'voyti'))
        )
?>
    <?= $form->end() ?>
</div>
</div>
