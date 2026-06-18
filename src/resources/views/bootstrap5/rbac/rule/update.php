<?php

declare(strict_types=1);

use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var \YiiRocks\Voyti\Form\RuleForm $model
 * @var array $errors
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-rbac-update">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.rule.update_title') ?></h2>
    <form method="post" novalidate>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $field => $fieldErrors): ?>
                    <?php foreach ((array) $fieldErrors as $error): ?>
                        <div><?= Html::encode($error) ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?= Text::widget()->name('rule[name]')->value($model->name)->label($translator->translate('voyti.view.name_label')) ?>
        <?= Text::widget()->name('rule[class]')->value($model->class)->label($translator->translate('voyti.view.rule.class_label')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.update_button')) ?>
    </form>
</div>
