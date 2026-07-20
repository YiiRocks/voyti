<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\ViewData\Session\ConfirmViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var LoginForm $form
 * @var ConfirmViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.title'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.two_factor.title'));

if ($data->method === 'email') {
    echo Html::p($translator->translate('voyti.view.two_factor_email.enter_code'));
}

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($form);

echo Html::p($translator->translate('voyti.view.two_factor.backup_code_hint'))->class('text-muted small');

$tabindex = 0;

echo Field::text($form, 'twoFactorAuthenticationCode')->addInputAttributes(['autocomplete' => 'one-time-code'])->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.two_factor.verify_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
