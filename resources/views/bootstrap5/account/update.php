<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var SettingsForm $model
 * @var ModuleConfig $config
 * @var User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.account.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['config' => $config, 'url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.account.title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/account-update'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

$tabindex = 0;

echo Field::text($model, 'username')->tabIndex(++$tabindex);

echo Field::email($model, 'email')->tabIndex(++$tabindex);

echo Field::password($model, 'password')->tabIndex(++$tabindex);

echo Field::password($model, 'passwordRepeat')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.save_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
