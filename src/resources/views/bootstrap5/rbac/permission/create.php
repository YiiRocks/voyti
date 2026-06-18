<?php

declare(strict_types=1);

use Yiisoft\Form\Field\ErrorSummary;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var \YiiRocks\Voyti\Form\PermissionForm $model
 * @var array $errors
 * @var array $unassignedItems
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-rbac-create">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.permission.create_title', category: 'voyti') ?></h2>
    <form action="<?= Html::encode($url->generate('voyti/permissions-create')) ?>" method="post" novalidate>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $field => $fieldErrors): ?>
                    <?php foreach ((array) $fieldErrors as $error): ?>
                        <div><?= Html::encode($error) ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?= Text::widget()->name('permission[name]')->label($translator->translate('voyti.view.name_label', category: 'voyti'))->value($model->name) ?>
        <?= Text::widget()->name('permission[description]')->label($translator->translate('voyti.view.description_label', category: 'voyti'))->value($model->description) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.create_button', category: 'voyti')) ?>
    </form>
</div>
