<?php

declare(strict_types=1);

use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var \YiiRocks\Voyti\Form\RoleForm $model
 * @var array $errors
 * @var array $unassignedItems
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-rbac-update">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.role.update_title', ['name' => Html::encode($model->itemName)]) ?></h2>
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
        <?= Text::widget()->name('role[name]')->value($model->name)->label($translator->translate('voyti.view.name_label')) ?>
        <?= Text::widget()->name('role[description]')->value($model->description)->label($translator->translate('voyti.view.description_label')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.update_button')) ?>
    </form>
</div>
