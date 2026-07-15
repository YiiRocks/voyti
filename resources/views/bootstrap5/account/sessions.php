<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserSessionHistory;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var list<UserSessionHistory> $sessions
 * @var string $currentSessionId
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var ?string $timezone
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.sessions.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['config' => $config, 'url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.sessions.title', category: 'voyti'));

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.session_history.ip', category: 'voyti'))->class('col-3');
echo Html::div($translator->translate('voyti.view.session_history.user_agent', category: 'voyti'))->class('col-5');
echo Html::div($translator->translate('voyti.view.session_history.last_seen', category: 'voyti'))->class('col-2');
echo Html::div()->class('col-2');
echo Html::div()->close();

foreach ($sessions as $session) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($session->getIp() ?? '')->class('col-3 text-break');
    echo Html::div($session->getUserAgent() ?? '')->class('col-5 text-break');
    echo Html::div(TimezoneHelper::formatLocalized($session->getUpdatedAt(), $translator->getLocale(), $timezone))->class('col-2');

    $isCurrentSession = $session->getSessionId() === $currentSessionId;
    echo Html::div()->class($isCurrentSession ? 'col-2 text-end' : 'col-2')->open();
    if ($isCurrentSession) {
        echo Html::span($translator->translate('voyti.view.sessions.this_device', category: 'voyti'))->class('badge bg-primary');
    } else {
        echo Html::form()
            ->post($url->generate('voyti/account-sessions-terminate', ['sessionId' => $session->getSessionId()]))
            ->csrf($csrf)
            ->open();
        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.sessions.revoke_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger'),
            );
        echo Html::form()->close();
    }
    echo Html::div()->close();

    echo Html::div()->close();
}

if ($sessions === []) {
    echo Html::p($translator->translate('voyti.view.sessions.none', category: 'voyti'));
}

echo Html::div()->close();
