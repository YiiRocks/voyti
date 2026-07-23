<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\TwoFactorCodeForm;
use YiiRocks\Voyti\ViewData\TwoFactor\GoogleSetupViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var GoogleSetupViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::p($translator->translate('voyti.view.two_factor.scan_qr'));

echo Html::div($data->qrCodeUri)
    ->id('voyti-2fa-qr')
    ->class('img-fluid mb-3')
    ->addStyle(['max-width' => '260px'])
    ->encode(false);

$renewButton = Html::button('&#128472;')
    ->id('voyti-2fa-renew')
    ->class('btn', 'btn-outline-secondary', 'btn-sm', 'ms-2')
    ->attribute('title', $data->renewLabel)
    ->attribute('aria-label', $data->renewLabel)
    ->encode(false)
    ->render();
echo Html::p($data->manualEntryLabel . ' ' . Html::code($data->secret)->id('voyti-2fa-secret')->render() . $renewButton)->encode(false);

/** @psalm-suppress InvalidScope */
echo $this->render('./_code-form', ['form' => $form, 'formSubmitUrl' => $data->formSubmitUrl, 'translator' => $translator, 'csrf' => $csrf]);
