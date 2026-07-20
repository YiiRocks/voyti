<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\TwoFactorCodeForm;
use YiiRocks\Voyti\ViewData\TwoFactor\EmailSetupViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var EmailSetupViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

if ($data->emailCodeSent) {
    echo Html::div($translator->translate('voyti.view.two_factor_email.enter_code'))->class('alert alert-info');

    /** @psalm-suppress InvalidScope */
    echo $this->render('./_code-form', ['form' => $form, 'formSubmitUrl' => $data->enableUrl, 'translator' => $translator, 'csrf' => $csrf]);
} else {
    echo Html::div()->class('alert alert-info')->open();
    echo Html::p($translator->translate('voyti.view.two_factor_email.confirm_intro'));
    echo Html::p(Html::strong($data->userEmail)->render())->encode(false);
    echo Html::div()->close();

    echo Html::form()
        ->post($data->sendCodeUrl)
        ->csrf($csrf)
        ->open();
    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.two_factor_email.send_button'))->class('btn', 'btn-primary')->attribute('tabindex', 1),
        );
    echo Html::form()->close();
}
