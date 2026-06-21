<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');

echo Html::ul()->class('nav nav-tabs mb-3')->open();
    echo Html::tag('li')->class('nav-item')->open();
        echo Html::a($translator->translate('voyti.view.settings.userProfile', category: 'voyti'), $url->generate('voyti/settings'))->class('nav-link');
    echo Html::tag('li')->close();

    echo Html::tag('li')->class('nav-item')->open();
        echo Html::a($translator->translate('voyti.view.settings.account', category: 'voyti'), $url->generate('voyti/settings-account'))->class('nav-link');
    echo Html::tag('li')->close();

    echo Html::tag('li')->class('nav-item')->open();
        echo Html::a($translator->translate('voyti.view.settings.networks', category: 'voyti'), $url->generate('voyti/settings-networks'))->class('nav-link');
    echo Html::tag('li')->close();

    if (!empty($config) && $config->enableGdprCompliance) {
        echo Html::tag('li')->class('nav-item')->open();
            echo Html::a($translator->translate('voyti.view.settings.privacy', category: 'voyti'), $url->generate('voyti/settings-privacy'))->class('nav-link');
        echo Html::tag('li')->close();
    }
echo Html::ul()->close();
