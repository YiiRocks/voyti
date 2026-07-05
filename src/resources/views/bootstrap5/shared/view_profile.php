<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var UserProfile $userProfile
 * @var TranslatorInterface $translator
 * @var string|null $profilePreviewClass
 * @var bool|null $showAdminFields
 */

$displayName = $userProfile->getName() ?: $user->getUsername();
$gravatarId = $userProfile->getGravatarId();
$profilePreviewClass ??= 'list-group mb-4';
$showAdminFields ??= false;

echo Html::ul()->class($profilePreviewClass)->open();

echo Html::li()->class('list-group-item text-center py-3')->open();
echo Html::h3(Html::encode($displayName))->class('h4 mb-3');
if ($gravatarId) {
    echo Html::img('https://www.gravatar.com/avatar/' . $gravatarId . '?s=256&d=mp')
        ->class('rounded-circle');
}
echo Html::li()->close();

if ($showAdminFields) {
    echo Html::li()->class('list-group-item list-group-item-primary')->open();
    echo Html::b($translator->translate('voyti.view.email_label', category: 'voyti'))->render() . ': ';
    echo Html::encode($user->getEmail());
    echo Html::li()->close();

    echo Html::li()->class('list-group-item list-group-item-primary')->open();
    echo Html::b($translator->translate('voyti.view.admin.registered_label', category: 'voyti'))->render() . ': ';
    echo date('Y-m-d H:i:s', $user->getCreatedAt());
    echo Html::li()->close();

    echo Html::li()->class('list-group-item list-group-item-primary')->open();
    echo Html::b($translator->translate('voyti.view.status_header', category: 'voyti'))->render() . ': ';
    if ($user->isBlocked()) {
        echo Html::span($translator->translate('voyti.view.status_blocked', category: 'voyti'))->class('badge', 'bg-danger');
    } elseif ($user->isConfirmed()) {
        echo Html::span($translator->translate('voyti.view.status_active', category: 'voyti'))->class('badge', 'bg-success');
    } else {
        echo Html::span($translator->translate('voyti.view.status_pending', category: 'voyti'))->class('badge', 'bg-warning text-dark');
    }
    echo Html::li()->close();
}

$publicEmail = $userProfile->getPublicEmail();
echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.public_email_label', category: 'voyti'))->render() . ': ';
echo $publicEmail ? Html::encode($publicEmail) : Html::span($translator->translate('voyti.view.not_set', category: 'voyti'))->class('text-muted fst-italic');
echo Html::li()->close();

$location = $userProfile->getLocation();
echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.location_label', category: 'voyti'))->render() . ': ';
echo $location ? Html::encode($location) : Html::span($translator->translate('voyti.view.not_set', category: 'voyti'))->class('text-muted fst-italic');
echo Html::li()->close();

$website = $userProfile->getWebsite();
echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.website_label', category: 'voyti'))->render() . ': ';
echo $website ? Html::a(Html::encode($website), $website)->rel('noopener noreferrer')->target('_blank') : Html::span($translator->translate('voyti.view.not_set', category: 'voyti'))->class('text-muted fst-italic');
echo Html::li()->close();

$timezone = $userProfile->getTimezone();
echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.timezone_label', category: 'voyti'))->render() . ': ';
echo $timezone ? Html::encode($timezone) : Html::span($translator->translate('voyti.view.not_set', category: 'voyti'))->class('text-muted fst-italic');
echo Html::li()->close();

$bio = $userProfile->getBio();
echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.bio_label', category: 'voyti'))->render() . ': ';
echo $bio ? nl2br(Html::encode($bio)) : Html::span($translator->translate('voyti.view.not_set', category: 'voyti'))->class('text-muted fst-italic');
echo Html::li()->close();

echo Html::ul()->close();
