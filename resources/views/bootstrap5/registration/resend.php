<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\ViewData\Registration\ResendViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var ResendForm $form
 * @var ResendViewData $data
 * @var TranslatorInterface $translator
 * @var Csrf $csrf
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
    ->buttonsData([
        [$translator->translate('voyti.view.reset_button'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
        [$translator->translate('voyti.view.send_button'), 'type' => 'submit', 'tabindex' => ++$tabindex],
    ]);

echo Html::form()->close();
echo Html::div()->close();
