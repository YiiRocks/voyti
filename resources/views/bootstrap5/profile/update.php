<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var SettingsForm $model
 * @var ModuleConfig $config
 * @var User $user
 * @var UserProfile $userProfile
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var bool $isSwitched
 * @var User|null $originalUser
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.edit_profile.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['config' => $config, 'url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

if ($isSwitched && $originalUser !== null) {
    echo Html::div()->class('alert alert-warning d-flex justify-content-between align-items-center')->open();
    echo Html::span(
        $translator->translate('voyti.view.admin.switched_banner', ['username' => $originalUser->getUsername()], category: 'voyti'),
    );
    echo Html::form()
        ->post($url->generate('voyti/admin-users-switch-identity-restore'))
        ->csrf($csrf)
        ->open();
    echo Html::submitButton(
        $translator->translate('voyti.view.admin.restore_button', category: 'voyti'),
    )->class('btn', 'btn-warning', 'btn-sm');
    echo Html::form()->close();
    echo Html::div()->close();
}

echo Html::H1($translator->translate('voyti.view.edit_profile.title', category: 'voyti'));
echo Html::div()->class('card border-primary mb-4')->open();
echo Html::div()->class('card-header bg-primary text-white')->open();
echo Html::H2($translator->translate('voyti.view.userProfile.title', category: 'voyti'))->class('h5 mb-0');
echo Html::div()->close();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/view_profile', [
    'user' => $user,
    'userProfile' => $userProfile,
    'translator' => $translator,
    'profilePreviewClass' => 'list-group list-group-flush',
]);
echo Html::div()->close();

echo Html::form()
    ->post($url->generate('voyti/profile-update'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

$tabindex = 0;

echo Field::text($model, 'name')->tabIndex(++$tabindex);

echo Field::email($model, 'publicEmail')->tabIndex(++$tabindex);

echo Field::email($model, 'gravatarEmail')->tabIndex(++$tabindex);

echo Field::date($model, 'birthday')->tabIndex(++$tabindex);

echo Field::text($model, 'location')->tabIndex(++$tabindex);

echo Field::text($model, 'website')->tabIndex(++$tabindex);

echo Field::select($model, 'timezone')
    ->prompt('')
    ->optionsData(TimezoneHelper::getAll())
    ->tabIndex(++$tabindex);

echo Field::textarea($model, 'bio')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.save_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
