<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
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
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::p($translator->translate('voyti.view.two_factor.scan_qr', category: 'voyti'));

if ($secret === null) {
    echo Html::div()->class('alert alert-warning')->open();
    echo $translator->translate('voyti.validator.two_factor_library_missing', category: 'voyti');
    echo Html::div()->close();
} else {
    if (!empty($qrCodeUri)) {
        echo Html::div()->id('voyti-2fa-qr')->class('img-fluid mb-3')->addStyle(['max-width' => '260px'])->open();
        echo $qrCodeUri;
        echo Html::div()->close();
    } else {
        echo Html::div()->class('alert alert-warning')->open();
        echo $translator->translate('voyti.view.two_factor.qr_unavailable', category: 'voyti');
        echo Html::div()->close();
    }

    $renewLabel = $translator->translate('voyti.view.two_factor.renew', category: 'voyti');
    $renewButton = Html::button(
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">'
        . '<path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>'
        . '<path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>'
        . '</svg>',
    )
        ->id('voyti-2fa-renew')
        ->class('btn', 'btn-outline-secondary', 'btn-sm', 'ms-2')
        ->attribute('title', $renewLabel)
        ->attribute('aria-label', $renewLabel)
        ->encode(false)
        ->render();
    echo Html::p($translator->translate('voyti.view.two_factor.manual_entry', category: 'voyti') . ' ' . Html::code($secret)->id('voyti-2fa-secret')->render() . $renewButton)->encode(false);

    /** @psalm-suppress InvalidScope */
    echo $this->render('./_code-form', ['method' => 'google', 'url' => $url, 'translator' => $translator, 'csrf' => $csrf]);
}
