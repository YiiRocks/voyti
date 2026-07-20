<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use YiiRocks\Voyti\ViewData\TwoFactor\BackupCodesViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var BackupCodesViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.backup_codes_title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.two_factor.backup_codes_title'));

echo Html::div()->class('alert alert-warning')->open();
echo $translator->translate('voyti.view.two_factor.backup_codes_intro');
echo Html::div()->close();

echo Html::ul()->class('list-group mb-3')->open();
foreach ($data->codes as $code) {
    echo Html::li($code)->class('list-group-item font-monospace');
}
echo Html::ul()->close();

echo Html::a(
    $translator->translate('voyti.view.two_factor.backup_codes_continue'),
    $data->continueUrl,
)->class('btn', 'btn-primary');

echo Html::div()->close();
