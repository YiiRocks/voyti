<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\RuleRow;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.rule.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../../shared/_admin-menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../../shared/_flash', ['flash' => $flash]);

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.rule.title'));
echo Html::a($translator->translate('voyti.view.rule.create_link'), $data->createUrl)->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.name_header'))->class('col-9');
echo Html::div($translator->translate('voyti.view.actions_header'))->class('col-3 text-end');
echo Html::div()->close();

foreach ($data->rules as $rule) {
    /** @var RuleRow $rule */
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($rule->name)->class('col-9');
    echo Html::div()->class('col-3 text-end')->open();
    echo Html::a($translator->translate('voyti.view.update_link'), $rule->updateUrl)->class('btn', 'btn-sm', 'btn-outline-secondary', 'me-1');

    echo Html::form()
        ->post($rule->formSubmitUrl)
        ->csrf($csrf)
        ->class('d-inline')
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.delete_button'))->class('btn', 'btn-sm', 'btn-outline-danger')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}
echo Html::div()->close();
