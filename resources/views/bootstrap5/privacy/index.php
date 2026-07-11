<?php

declare(strict_types=1);

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.privacy.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['config' => $config, 'url' => $url, 'translator' => $translator]);

echo Html::H1($translator->translate('voyti.view.privacy.title', category: 'voyti'));

echo Html::div()->class('list-group')->open();

if ($config->enableGdprCompliance) {
    echo Html::a($translator->translate('voyti.view.privacy.manage_gdpr_consent', category: 'voyti'), $url->generate('voyti/privacy-gdpr-consent'))->class('list-group-item', 'list-group-item-action');
    echo Html::a($translator->translate('voyti.view.privacy.export_data', category: 'voyti'), $url->generate('voyti/privacy-export'))->class('list-group-item', 'list-group-item-action');
    echo Html::a($translator->translate('voyti.view.privacy.anonymize_data', category: 'voyti'), $url->generate('voyti/privacy-anonymize'))->class('list-group-item', 'list-group-item-action');
}

if ($config->allowAccountDelete) {
    echo Html::a($translator->translate('voyti.view.privacy.delete_account', category: 'voyti'), $url->generate('voyti/privacy-delete'))->class('list-group-item', 'list-group-item-action', 'text-danger');
}

echo Html::div()->close();
echo Html::div()->close();
