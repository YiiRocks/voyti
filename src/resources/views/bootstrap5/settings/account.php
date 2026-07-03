<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var SettingsForm $model
 * @var ModuleConfig $config
 * @var User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.account.title', category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_menu.php';

echo Html::H1($translator->translate('voyti.view.account.title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/settings-account'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

echo Field::text($model, 'username');

echo Field::email($model, 'email');

echo Field::password($model, 'password');

echo Field::password($model, 'passwordRepeat');

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.save_button', category: 'voyti'))
    );

echo Html::form()->close();
echo Html::div()->close();
