<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\TwoFactorCodeForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var string $formSubmitUrl
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::form()
    ->post($formSubmitUrl)
    ->csrf($csrf)
    ->open();

$tabindex = 0;

echo Field::text($form, 'code')->addInputAttributes(['inputmode' => 'numeric'])->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.two_factor.enable'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
