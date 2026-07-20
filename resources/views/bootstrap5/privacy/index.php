<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Privacy\IndexViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.privacy.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);

echo Html::H1($translator->translate('voyti.view.privacy.title'));

echo Html::div()->class('list-group')->open();

if ($data->showGdprLinks) {
    echo Html::a($translator->translate('voyti.view.privacy.manage_gdpr_consent'), $data->gdprConsentUrl)->class('list-group-item', 'list-group-item-action');
    echo Html::a($translator->translate('voyti.view.privacy.export_data'), $data->exportUrl)->class('list-group-item', 'list-group-item-action');
    echo Html::a($translator->translate('voyti.view.privacy.anonymize_data'), $data->anonymizeUrl)->class('list-group-item', 'list-group-item-action');
}

if ($data->showDeleteLink) {
    echo Html::a($translator->translate('voyti.view.privacy.delete_account'), $data->deleteUrl)->class('list-group-item', 'list-group-item-action', 'text-danger');
}

echo Html::div()->close();
echo Html::div()->close();
