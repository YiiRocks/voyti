<?php

declare(strict_types=1);

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var list<string> $codes
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.backup_codes_title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['config' => $config, 'url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.two_factor.backup_codes_title', category: 'voyti'));

echo Html::div()->class('alert alert-warning')->open();
echo $translator->translate('voyti.view.two_factor.backup_codes_intro', category: 'voyti');
echo Html::div()->close();

echo Html::ul()->class('list-group mb-3')->open();
foreach ($codes as $code) {
    echo Html::li($code)->class('list-group-item font-monospace');
}
echo Html::ul()->close();

echo Html::a(
    $translator->translate('voyti.view.two_factor.backup_codes_continue', category: 'voyti'),
    $url->generate('voyti/two-factor'),
)->class('btn', 'btn-primary');

echo Html::div()->close();
