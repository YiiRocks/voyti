<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

$this->setTitle($translator->translate('voyti.view.privacy.title', category: 'voyti'));

echo Html::div()->class('voyti-privacy')->open();
echo Html::H1($translator->translate('voyti.view.privacy.title', category: 'voyti'));

echo Html::div()->class('list-group')->open();
echo Html::a($translator->translate('voyti.view.privacy.manage_gdpr_consent', category: 'voyti'), $url->generate('voyti/gdpr-consent'))->class('list-group-item', 'list-group-item-action');
echo Html::a($translator->translate('voyti.view.privacy.delete_data', category: 'voyti'), $url->generate('voyti/gdpr-delete'))->class('list-group-item', 'list-group-item-action');
echo Html::div()->close();
echo Html::div()->close();
