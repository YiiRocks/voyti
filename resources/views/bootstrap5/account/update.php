<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\ViewData\Account\UpdateViewData;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var SettingsForm $form
 * @var UpdateViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.account.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash');

echo Html::H1($translator->translate('voyti.view.account.title'));

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($form);

$tabindex = 0;

echo Field::text($form, 'username')->tabIndex(++$tabindex);

echo Field::email($form, 'email')->tabIndex(++$tabindex);

echo Field::password($form, 'password')->tabIndex(++$tabindex);

echo Field::password($form, 'passwordRepeat')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttonsData([
        [$translator->translate('voyti.view.reset_button'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
        [$translator->translate('voyti.view.save_button'), 'type' => 'submit', 'tabindex' => ++$tabindex],
    ]);

echo Html::form()->close();
echo Html::div()->close();
