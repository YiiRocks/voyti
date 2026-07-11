<?php

declare(strict_types=1);

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Model\UserSocialAccount;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var UserSocialAccount $account
 * @var AuthClientRegistry $authClients
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.registration.connect_title', category: 'voyti'));

$providerTitle = $authClients->getTitle($account->getProvider());

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.registration.connect_title', category: 'voyti'));
echo Html::p($translator->translate('voyti.view.registration.connect_provider', ['provider' => $providerTitle], category: 'voyti'));

echo Html::p($translator->translate('voyti.view.registration.connect_message', category: 'voyti'));

echo Html::a($translator->translate('voyti.view.registration.connect_login', category: 'voyti'), $url->generate('voyti/session-login'))->class('btn', 'btn-primary');

echo ' ';

echo Html::a($translator->translate('voyti.view.registration.connect_register', category: 'voyti'), $url->generate('voyti/registration-register'))->class('btn', 'btn-outline-secondary');
echo Html::div()->close();
