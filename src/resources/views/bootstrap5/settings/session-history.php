<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var ModuleConfig $config
 * @var array $sessions
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');

$this->setTitle($translator->translate('voyti.view.session_history.title', category: 'voyti'));

echo Html::div()->class('voyti-session-history')->open();
    Html::H3()->class('mb-3')->open();
        echo $translator->translate('voyti.view.session_history.title', category: 'voyti');
    echo Html::H3()->close();

    echo Html::div()->class('table-responsive')->open();
        echo Html::table()->class('table table-striped')->open();

        echo Html::tag('thead')->open();
            echo Html::tag('tr')->open();
                echo Html::tag('th', $translator->translate('voyti.view.session_history.ip', category: 'voyti'));
                echo Html::tag('th', $translator->translate('voyti.view.session_history.user_agent', category: 'voyti'));
                echo Html::tag('th', $translator->translate('voyti.view.session_history.created', category: 'voyti'));
            echo Html::tag('tr')->close();
        echo Html::tag('thead')->close();

        echo Html::tag('tbody')->open();

        foreach ($sessions as $session) {
            echo Html::tag('tr')->open();
                echo Html::tag('td', Html::encode($session->getIp() ?? ''));
                echo Html::tag('td', Html::encode($session->getUserAgent() ?? ''));
                echo Html::tag('td', date('Y-m-d H:i:s', $session->getCreatedAt()));
            echo Html::tag('tr')->close();
        }

        echo Html::tag('tbody')->close();

        echo Html::table()->close();
    echo Html::div()->close();
echo Html::div()->close();
