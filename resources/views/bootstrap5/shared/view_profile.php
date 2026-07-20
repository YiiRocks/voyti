<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\ProfileCardViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var ProfileCardViewData $profile
 * @var TranslatorInterface $translator
 */

echo Html::ul()->class($profile->profilePreviewClass)->open();

echo Html::li()->class('list-group-item text-center py-3')->open();
echo Html::h3(Html::encode($profile->displayName))->class('h4 mb-3');
if ($profile->gravatarUrl !== null) {
    echo Html::img($profile->gravatarUrl)->class('rounded-circle');
}
echo Html::li()->close();

if ($profile->showAdminFields) {
    echo Html::li()->class('list-group-item list-group-item-primary')->open();
    echo Html::b($translator->translate('voyti.view.email_label'))->render() . ': ';
    echo Html::encode((string) $profile->email);
    echo Html::li()->close();

    echo Html::li()->class('list-group-item list-group-item-primary')->open();
    echo Html::b($translator->translate('voyti.view.admin.registered_label'))->render() . ': ';
    echo $profile->registeredDisplay;
    echo Html::li()->close();

    echo Html::li()->class('list-group-item list-group-item-primary')->open();
    echo Html::b($translator->translate('voyti.view.status_header'))->render() . ': ';
    echo Html::span($profile->statusLabel)->class('badge', $profile->statusBadgeClass);
    echo Html::li()->close();
}

echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.public_email_label'))->render() . ': ';
echo $profile->publicEmail !== null ? Html::encode($profile->publicEmail) : Html::span($translator->translate('voyti.view.not_set'))->class('text-muted fst-italic');
echo Html::li()->close();

echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.location_label'))->render() . ': ';
echo $profile->location !== null ? Html::encode($profile->location) : Html::span($translator->translate('voyti.view.not_set'))->class('text-muted fst-italic');
echo Html::li()->close();

echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.website_label'))->render() . ': ';
echo $profile->website !== null ? Html::a(Html::encode($profile->website), $profile->website)->rel('noopener noreferrer')->target('_blank') : Html::span($translator->translate('voyti.view.not_set'))->class('text-muted fst-italic');
echo Html::li()->close();

echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.timezone_label'))->render() . ': ';
echo $profile->timezone !== null ? Html::encode($profile->timezone) : Html::span($translator->translate('voyti.view.not_set'))->class('text-muted fst-italic');
echo Html::li()->close();

echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.bio_label'))->render() . ': ';
echo $profile->bio !== null ? nl2br(Html::encode($profile->bio)) : Html::span($translator->translate('voyti.view.not_set'))->class('text-muted fst-italic');
echo Html::li()->close();

echo Html::ul()->close();
