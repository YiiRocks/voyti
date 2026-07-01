<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var UserProfile $userProfile
 * @var TranslatorInterface $translator
 */

$displayName = $userProfile->getName() ?: $user->getUsername();
/** @psalm-suppress InvalidScope */
$this->setTitle($displayName);

echo Html::div()->class('card')->open();
echo Html::div()->class('card-body')->open();
echo Html::H1($displayName);

$publicEmail = $userProfile->getPublicEmail();
if ($publicEmail !== null && $publicEmail !== '') {
    echo Html::p()->class('card-text')->open();
    echo Html::b($translator->translate('voyti.view.userProfile.email_label', category: 'voyti'))->render() . ': ' . Html::encode($publicEmail);
    echo Html::p()->close();
}

if ($userProfile->getLocation()) {
    echo Html::p()->class('card-text')->open();
    echo Html::b($translator->translate('voyti.view.userProfile.location_label', category: 'voyti'))->render() . ': ' . Html::encode($userProfile->getLocation());
    echo Html::p()->close();
}

if ($userProfile->getBio()) {
    echo Html::p()->class('card-text')->open();
    echo Html::b($translator->translate('voyti.view.userProfile.bio_label', category: 'voyti'))->render() . ': ' . nl2br(Html::encode($userProfile->getBio()));
    echo Html::p()->close();
}
echo Html::div()->close();
echo Html::div()->close();
