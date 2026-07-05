<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var UserProfileForm $model
 * @var User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.update_profile_title', category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_admin-menu.php';

echo Html::H1($translator->translate('voyti.view.admin.update_profile_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/admin-update-profile', ['id' => $user->getId()]))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

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
        Html::submitButton($translator->translate('voyti.view.update_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
