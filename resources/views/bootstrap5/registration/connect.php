<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Registration\ConnectViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var ConnectViewData $data
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.registration.connect_title'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.registration.connect_title'));
echo Html::p($translator->translate('voyti.view.registration.connect_provider', ['provider' => $data->providerTitle]));

echo Html::p($translator->translate('voyti.view.registration.connect_message'));

echo Html::a($translator->translate('voyti.view.registration.connect_login'), $data->loginUrl)->class('btn', 'btn-primary');

echo ' ';

echo Html::a($translator->translate('voyti.view.registration.connect_register'), $data->registerUrl)->class('btn', 'btn-outline-secondary');
echo Html::div()->close();
