<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var UserSessionHistory[] $sessions
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_admin-menu.php';

Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.admin.session_history', category: 'voyti');
echo Html::H3()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.session_history.ip', category: 'voyti'))->class('col-3');
echo Html::div($translator->translate('voyti.view.session_history.user_agent', category: 'voyti'))->class('col-6');
echo Html::div($translator->translate('voyti.view.session_history.created', category: 'voyti'))->class('col-3');
echo Html::div()->close();

foreach ($sessions as $session) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($session->getIp() ?? '')->class('col-3 text-break');
    echo Html::div($session->getUserAgent() ?? '')->class('col-6 text-break');
    echo Html::div(date('Y-m-d H:i:s', $session->getCreatedAt()))->class('col-3');
    echo Html::div()->close();
}

echo Html::form()
    ->post($url->generate('voyti/admin-terminate-sessions', ['id' => $user->getId()]))
    ->csrf($csrf)
    ->open();

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.admin.terminate_sessions', category: 'voyti'))->class('btn', 'btn-danger')->attribute('tabindex', 1),
    );

echo Html::form()->close();
echo Html::div()->close();
