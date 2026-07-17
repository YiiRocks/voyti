<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSession;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var UserSession[] $sessions
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.sessions', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.admin.sessions', category: 'voyti');
echo Html::H3()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.sessions.ip', category: 'voyti'))->class('col-3');
echo Html::div($translator->translate('voyti.view.sessions.user_agent', category: 'voyti'))->class('col-6');
echo Html::div($translator->translate('voyti.view.sessions.last_seen', category: 'voyti'))->class('col-3');
echo Html::div()->close();

$timezone = $user->getProfile()?->getTimezone();

foreach ($sessions as $session) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($session->getIp() ?? '')->class('col-3 text-break');
    echo Html::div($session->getUserAgent() ?? '')->class('col-6 text-break');
    echo Html::div(TimezoneHelper::formatLocalized($session->getUpdatedAt(), $translator->getLocale(), $timezone))->class('col-3');
    echo Html::div()->close();
}

echo Html::form()
    ->post($url->generate('voyti/admin-users-terminate-sessions', ['id' => $user->getId()]))
    ->csrf($csrf)
    ->open();

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.admin.terminate_sessions', category: 'voyti'))->class('btn', 'btn-danger')->attribute('tabindex', 1),
    );

echo Html::form()->close();
echo Html::div()->close();
