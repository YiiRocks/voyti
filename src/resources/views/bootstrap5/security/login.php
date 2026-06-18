<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\LoginForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */

$this->setTitle($translator->translate('voyti.view.login.title', category: 'voyti'));
?>
<div class="voyti-login">
    <h1><?= $translator->translate('voyti.view.login.title', category: 'voyti') ?></h1>
    <form action="<?= Html::encode($url->generate('voyti/login')) ?>" method="post" novalidate>
        <?= Field::errorSummary(null)->errors($errors) ?>
        <?= Field::text($model, 'login') ?>
        <?= Field::password($model, 'password') ?>
        <?= Field::checkbox($model, 'rememberMe') ?>
        <?= RecaptchaHelper::render($model, $config) ?>
        <?= Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.login.sign_in_button', category: 'voyti'))
            )
?>
        <p class="mt-3">
            <a href="<?= Html::encode($url->generate('voyti/forgot')) ?>"><?= $translator->translate('voyti.view.login.forgot_password', category: 'voyti') ?></a>
            <?php if ($config->enableRegistration): ?>
                | <a href="<?= Html::encode($url->generate('voyti/register')) ?>"><?= $translator->translate('voyti.view.login.register_link', category: 'voyti') ?></a>
            <?php endif; ?>
        </p>
    </form>
</div>
