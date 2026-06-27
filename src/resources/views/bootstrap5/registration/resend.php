<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Auth\ResendForm;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var ResendForm $model
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.registration.resend_title', category: 'voyti'));

echo Html::div()->class('voyti-resend')->open();
echo Html::H1($translator->translate('voyti.view.registration.resend_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/resend'))
    ->csrf($csrf)
    ->open();

echo Field::email($model, 'email');

echo RecaptchaHelper::render($model, $config);

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.send_button', category: 'voyti'))
    );

echo Html::form()->close();
echo Html::div()->close();
