<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Entity\User $user
 * @var array $sessions
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');

echo Html::div()->class('voyti-admin-session-history')->open();
    Html::H3()->class('mb-3')->open();
        echo $translator->translate('voyti.view.admin.session_history', category: 'voyti');
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

    echo Html::form()
        ->post($url->generate('voyti/admin-terminate-sessions', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.admin.terminate_sessions', category: 'voyti'))->class('btn', 'btn-danger')
        );

    echo Html::form()->close();
echo Html::div()->close();
