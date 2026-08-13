<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var array{
 *   menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 *   sessions: list<array{
 *     session: array{ip: string, userAgent: string, lastSeenDisplay: string},
 *     isCurrentSession: bool,
 *     formSubmitUrl: string,
 *   }>,
 * } $data
 * @var TranslatorInterface $translator
 * @var array{success: string|null, warning: string|null} $flash
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.sessions.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data['menu']]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash');

echo Html::H1($translator->translate('voyti.view.sessions.title'));

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.sessions.ip'))->class('col-3');
echo Html::div($translator->translate('voyti.view.sessions.user_agent'))->class('col-5');
echo Html::div($translator->translate('voyti.view.sessions.last_seen'))->class('col-2');
echo Html::div()->class('col-2');
echo Html::div()->close();

foreach ($data['sessions'] as $row) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($row['session']['ip'])->class('col-3 text-break');
    echo Html::div($row['session']['userAgent'])->class('col-5 text-break');
    echo Html::div($row['session']['lastSeenDisplay'])->class('col-2');

    echo Html::div()->class('col-2 text-end')->open();
    if ($row['isCurrentSession']) {
        echo Html::button($translator->translate('voyti.view.sessions.this_device'))
            ->class('btn', 'btn-sm', 'btn-outline-primary')
            ->disabled();
    } else {
        echo Html::form()
            ->post($row['formSubmitUrl'])
            ->csrf($csrf)
            ->open();
        echo Field::buttonGroup()
            ->buttonsData([
                [$translator->translate('voyti.view.sessions.revoke_button'), 'type' => 'submit', 'class' => 'btn btn-sm btn-danger'],
            ]);
        echo Html::form()->close();
    }
    echo Html::div()->close();

    echo Html::div()->close();
}

if ($data['sessions'] === []) {
    echo Html::p($translator->translate('voyti.view.sessions.none'));
}

echo Html::div()->close();
