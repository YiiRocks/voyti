<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Checkbox;
use Yiisoft\Form\Field\Email;
use Yiisoft\Form\Field\ErrorSummary;
use Yiisoft\Form\Field\Password;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\SettingsForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var YiiRocks\Voyti\Entity\User $user
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-account">
    <?php include dirname(__DIR__) . '/shared/_menu.php'; ?>
    <h2 class="mb-4"><?= $translator->translate('voyti.view.account.title', category: 'voyti') ?></h2>
    <form method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('settings[username]')->value($model->username)->label($translator->translate('voyti.view.username_label', category: 'voyti')) ?>
        <?= Email::widget()->name('settings[email]')->value($model->email)->label($translator->translate('voyti.view.email_label', category: 'voyti')) ?>
        <?= Password::widget()->name('settings[password]')->label($translator->translate('voyti.view.new_password_label', category: 'voyti')) ?>
        <?= Password::widget()->name('settings[currentPassword]')->label($translator->translate('voyti.view.current_password_label', category: 'voyti')) ?>
        <?php if ($config->enableTwoFactorAuth): ?>
            <fieldset>
                <legend class="h5"><?= $translator->translate('voyti.view.account.two_factor_title', category: 'voyti') ?></legend>
                <?php if ($user->isAuthTfEnabled()): ?>
                    <p><?= $translator->translate('voyti.view.account.two_factor_enabled', category: 'voyti') ?></p>
                    <?= Checkbox::widget()->name('settings[authTfEnabled]')->inputValue('0')->label($translator->translate('voyti.view.account.disable_two_factor', category: 'voyti')) ?>
                <?php else: ?>
                    <?= Checkbox::widget()->name('settings[authTfEnabled]')->inputValue('1')->label($translator->translate('voyti.view.account.enable_two_factor', category: 'voyti')) ?>
                <?php endif; ?>
            </fieldset>
        <?php endif; ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.save_button', category: 'voyti')) ?>
    </form>
</div>
