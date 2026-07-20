<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\ViewData\Admin\User\ProfileViewData;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var UserProfileForm $form
 * @var ProfileViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.update_profile_title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.admin.update_profile_title'));

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($form);

$tabindex = 0;

echo Field::text($form, 'name')->tabIndex(++$tabindex);

echo Field::email($form, 'publicEmail')->tabIndex(++$tabindex);

echo Field::email($form, 'gravatarEmail')->tabIndex(++$tabindex);

echo Field::date($form, 'birthday')->tabIndex(++$tabindex);

echo Field::text($form, 'location')->tabIndex(++$tabindex);

echo Field::text($form, 'website')->tabIndex(++$tabindex);

echo Field::select($form, 'timezone')
    ->prompt('')
    ->optionsData($data->timezoneOptions)
    ->tabIndex(++$tabindex);

echo Field::textarea($form, 'bio')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.update_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
