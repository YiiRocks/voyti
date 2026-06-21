<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\RecoveryForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.recovery.request_title', category: 'voyti'));

echo Html::div()->class('voyti-recovery')->open();
    Html::H1($translator->translate('voyti.view.recovery.request_title', category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/forgot'))
        ->csrf($csrf)
        ->open();

    echo Field::errorSummary(null)->errors($errors);

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
