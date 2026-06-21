<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

echo Html::ul()->class('nav nav-pills mb-4')->open();
    echo Html::tag('li')->class('nav-item')->open();
        echo Html::a($translator->translate('voyti.menu.profile', category: 'voyti'), $url->generate('voyti/settings'))->class('nav-link');
    echo Html::tag('li')->close();

    echo Html::tag('li')->class('nav-item')->open();
        echo Html::a($translator->translate('voyti.menu.account', category: 'voyti'), $url->generate('voyti/settings-account'))->class('nav-link');
    echo Html::tag('li')->close();

    echo Html::tag('li')->class('nav-item')->open();
        echo Html::a($translator->translate('voyti.menu.networks', category: 'voyti'), $url->generate('voyti/settings-networks'))->class('nav-link');
    echo Html::tag('li')->close();
echo Html::ul()->close();
