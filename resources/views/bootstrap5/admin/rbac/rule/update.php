<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var RuleForm $form
 * @var array{
 *   menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 *   formSubmitUrl: string,
 *   errors: array<string, list<string>>,
 * } $data
 * @var TranslatorInterface $translator
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.rule.update_title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../../shared/_admin-menu', ['menu' => $data['menu']]);

echo Html::H1($translator->translate('voyti.view.rule.update_title'));

echo Html::form()
    ->post($data['formSubmitUrl'])
    ->csrf($csrf)
    ->open();

/** @psalm-suppress RiskyTruthyFalsyComparison */
if (!empty($data['errors'])) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($data['errors'] as $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

$tabindex = 0;

echo Field::text($form, 'name')->tabIndex(++$tabindex);

echo Field::text($form, 'class')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttonsData([
        [$translator->translate('voyti.view.reset_button'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
        [$translator->translate('voyti.view.update_button'), 'type' => 'submit', 'tabindex' => ++$tabindex],
    ]);

echo Html::form()->close();
echo Html::div()->close();
