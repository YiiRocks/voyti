<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var RegistrationForm $form
 * @var array{
 *   menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 *   formSubmitUrl: string,
 *   usernameValue: string,
 *   emailValue: string,
 *   items: list<array{name: string, checked: bool}>,
 *   errors: array<string, list<string>>,
 * } $data
 * @var TranslatorInterface $translator
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.create_user_title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data['menu']]);

echo Html::H1($translator->translate('voyti.view.admin.create_user_title'));

echo Html::form()
    ->post($data['formSubmitUrl'])
    ->csrf($csrf)
    ->open();

echo Field::errorSummary(null)->errors($data['errors']);

$tabindex = 0;

echo Field::text($form, 'username')->name('user[username]')->value($data['usernameValue'])->tabIndex(++$tabindex);

echo Field::email($form, 'email')->name('user[email]')->value($data['emailValue'])->tabIndex(++$tabindex);

echo Field::password($form, 'password')->name('user[password]')->tabIndex(++$tabindex);

echo Html::h3($translator->translate('voyti.view.assignments.title'))->class('mb-3');

foreach ($data['items'] as $item) {
    echo Html::div()->class('form-check')->open();
    $checkbox = Html::input('checkbox')->class('form-check-input')->name('assignedItems[]')->value($item['name'])->attribute('tabindex', ++$tabindex);
    if ($item['checked']) {
        $checkbox = $checkbox->addAttributes(['checked' => true]);
    }
    echo $checkbox;
    echo Html::label($item['name'])->class('form-check-label');
    echo Html::div()->close();
}

echo Field::buttonGroup()
    ->buttonsData([
        [$translator->translate('voyti.view.reset_button'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
        [$translator->translate('voyti.view.create_button'), 'type' => 'submit', 'tabindex' => ++$tabindex],
    ]);

echo Html::form()->close();
echo Html::div()->close();
