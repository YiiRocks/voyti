<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var string $method
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::form()
    ->post($url->generate('voyti/settings-two-factor-google-enable'))
    ->csrf($csrf)
    ->open();

echo Html::hiddenInput('method', $method);

$tabindex = 0;

echo Html::div()->class('mb-3')->open();
echo Html::label($translator->translate('voyti.view.two_factor.enter_code', category: 'voyti'))->class('form-label');
echo Html::textInput('code')->class('form-control')->required()->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.two_factor.enable', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
