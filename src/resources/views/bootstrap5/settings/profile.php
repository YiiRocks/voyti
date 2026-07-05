<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
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
 * @var array<string, list<string>> $errors
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.edit_profile.title', category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_menu.php';

echo Html::H1($translator->translate('voyti.view.edit_profile.title', category: 'voyti'));
echo Html::div()->class('card border-primary mb-4')->open();
echo Html::div()->class('card-header bg-primary text-white')->open();
echo Html::H2($translator->translate('voyti.view.userProfile.title', category: 'voyti'))->class('h5 mb-0');
echo Html::div()->close();
$profilePreviewClass = 'list-group list-group-flush';
include dirname(__DIR__) . '/shared/view_profile.php';
echo Html::div()->close();

echo Html::form()
    ->post($url->generate('voyti/settings'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary(null)->errors($errors);

$tabindex = 0;

echo Field::text($model, 'name')->tabIndex(++$tabindex);

echo Field::email($model, 'publicEmail')->tabIndex(++$tabindex);

echo Field::email($model, 'gravatarEmail')->tabIndex(++$tabindex);

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
