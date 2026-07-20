<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\ViewData\Profile\UpdateViewData;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var UserProfileForm $form
 * @var UpdateViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.edit_profile.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

if ($data->switchedBannerMessage !== null) {
    echo Html::div()->class('alert alert-warning d-flex justify-content-between align-items-center')->open();
    echo Html::span($data->switchedBannerMessage);
    echo Html::form()
        ->post($data->switchIdentityRestoreUrl)
        ->csrf($csrf)
        ->open();
    echo Html::submitButton(
        $translator->translate('voyti.view.admin.restore_button'),
    )->class('btn', 'btn-warning', 'btn-sm');
    echo Html::form()->close();
    echo Html::div()->close();
}

echo Html::H1($translator->translate('voyti.view.edit_profile.title'));
echo Html::div()->class('card border-primary mb-4')->open();
echo Html::div()->class('card-header bg-primary text-white')->open();
echo Html::H2($translator->translate('voyti.view.userProfile.title'))->class('h5 mb-0');
echo Html::div()->close();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/view_profile', [
    'profile' => $data->profile,
    'translator' => $translator,
]);
echo Html::div()->close();

echo Html::form()
    ->post($data->updateUrl)
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
        Html::submitButton($translator->translate('voyti.view.save_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
