<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array<string, mixed> $data
 * @var TranslatorInterface $translator
 * @var array{success: string|null, warning: string|null} $flash
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.settings.dashboard_title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data['menu']]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash');

echo Html::h1($translator->translate('voyti.view.settings.welcome', ['name' => $data['displayName']]))->class('h3 mb-3');

echo Html::ul()->class('list-group mb-4')->open();
echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.email_label'))->render() . ': ';
echo Html::encode($data['email']);
echo Html::li()->close();
echo Html::li()->class('list-group-item')->open();
echo Html::b($translator->translate('voyti.view.settings.member_since'))->render() . ': ';
echo Html::encode($data['memberSinceDisplay']);
echo Html::li()->close();
echo Html::ul()->close();

echo Html::div()->close();
