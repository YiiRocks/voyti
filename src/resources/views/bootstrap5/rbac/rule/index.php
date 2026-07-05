<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array $rules Array of rule class names (string[])
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.rule.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.rule.title', category: 'voyti'));
echo Html::a($translator->translate('voyti.view.rule.create_link', category: 'voyti'), $url->generate('voyti/rules-create'))->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.name_header', category: 'voyti'))->class('col-9');
echo Html::div($translator->translate('voyti.view.actions_header', category: 'voyti'))->class('col-3 text-end');
echo Html::div()->close();

/** @var string $ruleName */
foreach ($rules as $ruleName) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($ruleName)->class('col-9');
    echo Html::div()->class('col-3 text-end')->open();
    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/rules-update', ['name' => $ruleName]))->class('btn', 'btn-sm', 'btn-outline-secondary', 'me-1');

    echo Html::form()
        ->post($url->generate('voyti/rules-delete', ['name' => $ruleName]))
        ->csrf($csrf)
        ->class('d-inline')
        ->open();
    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')->attribute('tabindex', 1),
        );
    echo Html::form()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}
echo Html::div()->close();
