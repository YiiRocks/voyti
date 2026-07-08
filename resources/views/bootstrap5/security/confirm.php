<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var LoginForm $model
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 * @var string $method
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

if ($method === 'email') {
    echo Html::p($translator->translate('voyti.view.two_factor_email.enter_code', category: 'voyti'));
}

echo Html::form()
    ->post($url->generate('voyti/confirm'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

$tabindex = 0;

echo Field::text($model, 'twoFactorAuthenticationCode')->inputAttributes(['autocomplete' => 'one-time-code'])->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.two_factor.verify_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
