<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\ViewData\Registration\ResendViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var ResendForm $form
 * @var ResendViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.registration.resend_title'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.registration.resend_title'));

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

$tabindex = 0;

echo Field::email($form, 'email')->tabIndex(++$tabindex);

echo $data->recaptchaFieldHtml;

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.send_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
