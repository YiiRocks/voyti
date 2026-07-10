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
 * @var bool $emailCodeSent
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

if ($emailCodeSent) {
    echo Html::div()->class('alert alert-info')->open();
    echo $translator->translate('voyti.view.two_factor_email.enter_code', category: 'voyti');
    echo Html::div()->close();

    /** @psalm-suppress InvalidScope */
    echo $this->render('./_code-form', ['method' => 'email', 'url' => $url, 'translator' => $translator, 'csrf' => $csrf]);
} else {
    echo Html::div()->class('alert alert-info')->open();
    echo Html::p($translator->translate('voyti.view.two_factor_email.confirm_intro', category: 'voyti'));
    echo Html::p(Html::strong($user->getEmail())->render())->encode(false);
    echo Html::div()->close();

    echo Html::form()
        ->post($url->generate('voyti/settings-two-factor-email-send-code'))
        ->csrf($csrf)
        ->open();
    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.two_factor_email.send_button', category: 'voyti'))->class('btn', 'btn-primary')->attribute('tabindex', 1),
        );
    echo Html::form()->close();
}
