<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Admin\User\SessionsViewData;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var SessionsViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.sessions'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.admin.sessions');
echo Html::H3()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.sessions.ip'))->class('col-3');
echo Html::div($translator->translate('voyti.view.sessions.user_agent'))->class('col-5');
echo Html::div($translator->translate('voyti.view.sessions.last_seen'))->class('col-2');
echo Html::div()->class('col-2');
echo Html::div()->close();

foreach ($data->sessions as $session) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($session->ip)->class('col-3 text-break');
    echo Html::div($session->userAgent)->class('col-5 text-break');
    echo Html::div($session->lastSeenDisplay)->class('col-2');
    echo Html::div()->class('col-2 text-end')->open();
    if ($session->isRevoked) {
        $revokedButton = Html::button($translator->translate('voyti.view.sessions.revoked'))
            ->class('btn', 'btn-sm', 'btn-outline-secondary')
            ->disabled();
        echo Html::span($revokedButton)->attribute('title', $session->revokedAtDisplay);
    } else {
        echo Html::button($translator->translate('voyti.view.sessions.active'))
            ->class('btn', 'btn-sm', 'btn-outline-success')
            ->disabled();
    }
    echo Html::div()->close();
    echo Html::div()->close();
}

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.admin.terminate_sessions'))->class('btn', 'btn-danger')->attribute('tabindex', 1),
    );

echo Html::form()->close();
echo Html::div()->close();
