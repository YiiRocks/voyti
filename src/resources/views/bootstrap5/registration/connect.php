<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\SocialNetworkAccount;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var SocialNetworkAccount $account
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');

echo Html::div()->class('voyti-registration-connect')->open();
    Html::H1($translator->translate('voyti.view.registration.connect_title', category: 'voyti'));

    echo Html::p($translator->translate('voyti.view.registration.connect_message', category: 'voyti'));

    echo Html::a($translator->translate('voyti.view.registration.connect_login', category: 'voyti'), $url->generate('voyti/login'))->class('btn', 'btn-primary');

    echo ' ';

    echo Html::a($translator->translate('voyti.view.registration.connect_register', category: 'voyti'), $url->generate('voyti/register'))->class('btn', 'btn-outline-secondary');
echo Html::div()->close();
