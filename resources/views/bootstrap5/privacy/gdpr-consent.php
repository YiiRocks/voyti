<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var GdprConsentForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.gdpr.consent_title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);
echo Html::H1($translator->translate('voyti.view.gdpr.consent_title', category: 'voyti'));

$isLocked = $model->consent;

if ($isLocked) {
    $consentDate = $model->consentDate !== null ? TimezoneHelper::formatLocalized($model->consentDate, $translator->getLocale(), $model->timezone) : '';
    echo Html::p($translator->translate('voyti.view.gdpr.consent_locked', ['date' => $consentDate], category: 'voyti'))->class('text-muted');
}

echo Html::form()
    ->post($url->generate('voyti/privacy-gdpr-consent'))
    ->csrf($csrf)
    ->open();

$tabindex = 0;

echo Field::checkbox($model, 'consent')->tabIndex(++$tabindex)->disabled($isLocked);

if (!$isLocked) {
    echo Field::buttonGroup()
        ->buttons(
            Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
            Html::submitButton($translator->translate('voyti.view.save_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
        );
}

echo Html::form()->close();
echo Html::div()->close();
