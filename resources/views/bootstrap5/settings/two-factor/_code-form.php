<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Settings\TwoFactorCodeForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::form()
    ->post($url->generate('voyti/settings-two-factor-google-enable'))
    ->csrf($csrf)
    ->open();

//echo Html::hiddenInput('method', $form->method);

$tabindex = 0;

echo Field::text($form, 'code')->addInputAttributes(['inputmode' => 'numeric'])->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.two_factor.enable', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
