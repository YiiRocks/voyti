<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\ViewData\Registration\RegisterViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var RegistrationForm $form
 * @var RegisterViewData $data
 * @var TranslatorInterface $translator
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.registration.register_title'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.registration.register_title'));

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($form);

$tabindex = 0;

echo Field::text($form, 'username')->tabIndex(++$tabindex);

echo Field::email($form, 'email')->tabIndex(++$tabindex);

echo Field::password($form, 'password')->tabIndex(++$tabindex);

echo Field::password($form, 'passwordRepeat')->tabIndex(++$tabindex);

if ($data->showGdprConsent) {
    echo Field::checkbox($form, 'gdprConsent')->tabIndex(++$tabindex);
}

echo $data->recaptchaFieldHtml;

echo Field::buttonGroup()
    ->buttonsData([
        [$translator->translate('voyti.view.reset_button'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
        [$translator->translate('voyti.view.registration.register_button'), 'type' => 'submit', 'tabindex' => ++$tabindex],
    ]);

echo Html::div()->class('mt-3')->open();
echo Html::a($translator->translate('voyti.view.registration.already_have_account'), $data->loginUrl);
echo Html::div()->close();

echo Html::form()->close();
echo Html::div()->close();
