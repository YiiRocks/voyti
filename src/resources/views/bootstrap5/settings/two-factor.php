<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var string $qrCodeUri
 * @var string|null $secret
 * @var ModuleConfig $config
 * @var array<string, list<string>> $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_menu.php';

echo Html::H1($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

if (!empty($errors)) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($errors as $field => $fieldErrors) {
        /** @var string $error */
        foreach ($fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

if ($user->isAuthTfEnabled()) {
    echo Html::p($translator->translate('voyti.view.two_factor.enabled', category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/settings-two-factor-disable'))
        ->csrf($csrf)
        ->open();

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.two_factor.disable', category: 'voyti'))->class('btn', 'btn-danger')
        );

    echo Html::form()->close();
} else {
    echo Html::p($translator->translate('voyti.view.two_factor.scan_qr', category: 'voyti'));

    if (!empty($qrCodeUri)) {
        echo Html::div()->class('img-fluid mb-3')->addStyle(['max-width' => '260px'])->open();
        echo $qrCodeUri;
        echo Html::div()->close();

        if (!empty($secret)) {
            echo Html::p($translator->translate('voyti.view.two_factor.manual_entry', category: 'voyti') . ' ' . Html::code($secret)->render())->encode(false);
        }
    } else {
        echo Html::div()->class('alert alert-warning')->open();
        echo $translator->translate('voyti.view.two_factor.qr_unavailable', category: 'voyti');
        echo Html::div()->close();
    }

    echo Html::form()
        ->post($url->generate('voyti/settings-two-factor-enable'))
        ->csrf($csrf)
        ->open();

    echo Html::div()->class('mb-3')->open();
    echo Html::label($translator->translate('voyti.view.two_factor.enter_code', category: 'voyti'))->class('form-label');
    echo Html::textInput('code')->class('form-control')->required();
    echo Html::div()->close();

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.two_factor.enable', category: 'voyti'))->class('btn', 'btn-primary')
        );

    echo Html::form()->close();
}
echo Html::div()->close();
