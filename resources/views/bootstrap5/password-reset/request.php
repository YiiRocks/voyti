<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\ViewData\PasswordReset\RequestViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var RecoveryForm $form
 * @var RequestViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.recovery.request_title'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.recovery.request_title'));

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($form);

$tabindex = 0;

echo Field::email($form, 'email')->tabIndex(++$tabindex);

echo $data->recaptchaFieldHtml;

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.recovery.send_link_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::div()->class('mt-3')->open();
echo Html::a($translator->translate('voyti.view.recovery.back_to_login'), $data->loginUrl);
echo Html::div()->close();

echo Html::form()->close();
echo Html::div()->close();
