<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Settings\SettingsForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var YiiRocks\Voyti\Entity\User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.account.title', category: 'voyti'));

echo Html::div()->class('voyti-account')->open();
    include dirname(__DIR__) . '/shared/_menu.php';

    echo Html::H1($translator->translate('voyti.view.account.title', category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/settings-account'))
        ->csrf($csrf)
        ->open();

    echo Field::errorSummary(null)->errors($errors);

    echo Field::text($model, 'username');

    echo Field::email($model, 'email');

    echo Field::password($model, 'password');

    echo Field::password($model, 'currentPassword');

    if ($config->enableTwoFactorAuth) {
        echo '<fieldset>' . "\n";
        echo '    <legend class="h5">' . $translator->translate('voyti.view.account.two_factor_title', category: 'voyti') . '</legend>' . "\n";

        if ($user->isAuthTfEnabled()) {
            echo '    <p>' . $translator->translate('voyti.view.account.two_factor_enabled', category: 'voyti') . '</p>' . "\n";
            echo Field::checkbox($model, 'authTfEnabled')->label($translator->translate('voyti.view.account.disable_two_factor', category: 'voyti'));
        } else {
            echo Field::checkbox($model, 'authTfEnabled')->label($translator->translate('voyti.view.account.enable_two_factor', category: 'voyti'));
        }

        echo '</fieldset>' . "\n";
    }

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.save_button', category: 'voyti'))
        );

    echo Html::form()->close();
echo Html::div()->close();
