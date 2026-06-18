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
 * @var YiiRocks\Voyti\Form\RegistrationForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-register">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.registration.register_title') ?></h2>
    <form action="<?= Html::encode($url->generate('voyti/register')) ?>" method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('register[username]')->value($model->username)->label($translator->translate('voyti.view.username_label')) ?>
        <?= Email::widget()->name('register[email]')->value($model->email)->label($translator->translate('voyti.view.email_label')) ?>
        <?= Password::widget()->name('register[password]')->label($translator->translate('voyti.view.password_label')) ?>
        <?php if ($config->enableGdprCompliance): ?>
            <?= Checkbox::widget()->name('register[gdprConsent]')->inputValue('1')->label($translator->translate('voyti.view.registration.gdpr_consent_label')) ?>
        <?php endif; ?>
        <?= \YiiRocks\Voyti\Helper\RecaptchaHelper::render($model, $config) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.registration.register_button')) ?>
        <p class="mt-3"><a href="<?= Html::encode($url->generate('voyti/login')) ?>"><?= $translator->translate('voyti.view.registration.already_have_account') ?></a></p>
    </form>
</div>
