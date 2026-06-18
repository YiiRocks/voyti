<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Checkbox;
use Yiisoft\Form\Field\ErrorSummary;
use Yiisoft\Form\Field\Password;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\LoginForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-login">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.login.title', category: 'voyti') ?></h2>
    <form action="<?= Html::encode($url->generate('voyti/login')) ?>" method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('login[login]')->value($model->login)->label($translator->translate('voyti.view.login.login_label', category: 'voyti')) ?>
        <?= Password::widget()->name('login[password]')->label($translator->translate('voyti.view.password_label', category: 'voyti')) ?>
        <?= Checkbox::widget()->name('login[rememberMe]')->inputValue('1')->label($translator->translate('voyti.view.login.remember_me', category: 'voyti')) ?>
        <?= \YiiRocks\Voyti\Helper\RecaptchaHelper::render($model, $config) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.login.sign_in_button', category: 'voyti')) ?>
        <p class="mt-3">
            <a href="<?= Html::encode($url->generate('voyti/forgot')) ?>"><?= $translator->translate('voyti.view.login.forgot_password', category: 'voyti') ?></a>
            <?php if ($config->enableRegistration): ?>
                | <a href="<?= Html::encode($url->generate('voyti/register')) ?>"><?= $translator->translate('voyti.view.login.register_link', category: 'voyti') ?></a>
            <?php endif; ?>
        </p>
    </form>
</div>
