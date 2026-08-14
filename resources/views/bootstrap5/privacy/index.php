<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array{
 *   menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 *   showDeleteLink: bool,
 *   deleteUrl: ?string,
 *   privacyLinks: list<array{label: string, url: string}>,
 * } $data
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.privacy.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data['menu']]);

echo Html::H1($translator->translate('voyti.view.privacy.title'));

echo Html::div()->class('list-group')->open();

foreach ($data['privacyLinks'] as $link) {
    echo Html::a($link['label'], $link['url'])->class('list-group-item', 'list-group-item-action');
}

if ($data['showDeleteLink']) {
    echo Html::a($translator->translate('voyti.view.privacy.delete_account'), $data['deleteUrl'])->class('list-group-item', 'list-group-item-action', 'text-danger');
}

echo Html::div()->close();
echo Html::div()->close();
