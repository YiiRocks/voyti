<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var RecoveryForm $model
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.recovery.request_title', category: 'voyti'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.recovery.request_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/forgot'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

echo Field::email($model, 'email');

echo RecaptchaHelper::render($model, $config);

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.recovery.send_link_button', category: 'voyti'))
    );

echo Html::div()->class('mt-3')->open();
echo Html::a($translator->translate('voyti.view.recovery.back_to_login', category: 'voyti'), $url->generate('voyti/login'));
echo Html::div()->close();

echo Html::form()->close();
echo Html::div()->close();
