<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var string $title
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($title);

echo Html::div()->class('card shadow-sm')->open();
    echo Html::div()->class('card-body text-center py-5')->open();
        echo Html::H1($title);

        echo Html::a($translator->translate('voyti.view.go_home', category: 'voyti'), '/')->class('btn', 'btn-primary');
    echo Html::div()->close();
echo Html::div()->close();
