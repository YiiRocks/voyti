<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\ViewData\Privacy\GdprConsentViewData;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var GdprConsentForm $form
 * @var GdprConsentViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.gdpr.consent_title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash');
echo Html::H1($translator->translate('voyti.view.gdpr.consent_title'));

if ($data->isLocked) {
    echo Html::p($translator->translate('voyti.view.gdpr.consent_locked', ['date' => $data->consentDateDisplay ?? '']))->class('text-muted');
}

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

$tabindex = 0;

echo Field::checkbox($form, 'consent')->tabIndex(++$tabindex)->disabled($data->isLocked);

if (!$data->isLocked) {
    echo Field::buttonGroup()
        ->buttonsData([
            [$translator->translate('voyti.view.reset_button'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
            [$translator->translate('voyti.view.save_button'), 'type' => 'submit', 'tabindex' => ++$tabindex],
        ]);
}

echo Html::form()->close();
echo Html::div()->close();
