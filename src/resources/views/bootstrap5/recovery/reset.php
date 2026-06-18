<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Password;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\RecoveryForm $model
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-reset">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.recovery.reset_title', category: 'voyti') ?></h2>
    <form method="post" novalidate>
        <?= Password::widget()->name('recovery[password]')->label($translator->translate('voyti.view.new_password_label', category: 'voyti')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.recovery.reset_button', category: 'voyti')) ?>
    </form>
</div>
