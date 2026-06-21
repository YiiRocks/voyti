<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\LoginForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

echo Html::div()->class('voyti-2fa')->open();
    Html::H1($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/confirm'))
        ->csrf($csrf)
        ->open();

    echo Field::text($model, 'twoFactorAuthenticationCode')->inputAttributes(['autocomplete' => 'one-time-code']);

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.two_factor.verify_button', category: 'voyti'))
        );

    echo Html::form()->close();
echo Html::div()->close();
