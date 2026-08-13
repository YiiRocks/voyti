<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\ConsentForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var ConsentForm $form
 * @var array{
 *   formSubmitUrl: string,
 * } $data
 * @var TranslatorInterface $translator
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.delete_account.title'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.delete_account.title'));

echo Html::p()->class('alert alert-danger')->open();
echo $translator->translate('voyti.view.delete_account.warning');
echo Html::p()->close();

echo Html::form()
    ->post($data['formSubmitUrl'])
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($form);

$tabindex = 0;

echo Field::password($form, 'password')->tabIndex(++$tabindex);

echo Field::checkbox($form, 'consent')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttonsData([
        [$translator->translate('voyti.view.reset_button'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
        [$translator->translate('voyti.view.delete_account.button'), 'type' => 'submit', 'class' => 'btn btn-danger', 'tabindex' => ++$tabindex],
    ]);

echo Html::form()->close();
echo Html::div()->close();
