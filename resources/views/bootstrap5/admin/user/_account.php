<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\ViewData\Admin\User\AccountViewData;
use YiiRocks\Voyti\ViewData\Shared\AssignableItemRow;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var SettingsForm $form
 * @var AccountViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($data->title);

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);

echo Html::H1($data->title);

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

echo Field::errorSummary(null)->errors($data->errors);

$tabindex = 0;

echo Field::text($form, 'username')->name('user[username]')->value($data->usernameValue)->tabIndex(++$tabindex);

echo Field::email($form, 'email')->name('user[email]')->value($data->emailValue)->tabIndex(++$tabindex);

echo Field::password($form, 'password')->name('user[password]')->tabIndex(++$tabindex);

echo Html::h3($translator->translate('voyti.view.assignments.title'))->class('mb-3');

foreach ($data->items as $item) {
    /** @var AssignableItemRow $item */
    echo Html::div()->class('form-check')->open();
    $checkbox = Html::input('checkbox')->class('form-check-input')->name('assignedItems[]')->value($item->name)->attribute('tabindex', ++$tabindex);
    if ($item->checked) {
        $checkbox = $checkbox->addAttributes(['checked' => true]);
    }
    echo $checkbox;
    echo Html::label($item->name)->class('form-check-label');
    echo Html::div()->close();
}

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.update_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
