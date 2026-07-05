<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var SettingsForm $model
 * @var User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array<string, list<string>> $errors
 * @var string $csrf
 * @var array<string, Permission|Role> $allItems
 * @var list<string> $assignedItems
 */

$username = $user->getUsername();

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.update_user_title', ['username' => $username], category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);

echo Html::H1($translator->translate('voyti.view.admin.update_user_title', ['username' => $username], category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/admin-update', ['id' => $user->getId()]))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary(null)->errors($errors);

$tabindex = 0;

echo Field::text($model, 'username')->name('user[username]')->value($model->username)->tabIndex(++$tabindex);

echo Field::email($model, 'email')->name('user[email]')->value($model->email)->tabIndex(++$tabindex);

echo Field::password($model, 'password')->name('user[password]')->tabIndex(++$tabindex);

echo Html::h3($translator->translate('voyti.view.assignments.title', category: 'voyti'))->class('mb-3');

foreach ($allItems as $name => $item) {
    echo Html::div()->class('form-check')->open();
    $checkbox = Html::input('checkbox')->class('form-check-input')->name('assignedItems[]')->value($name)->attribute('tabindex', ++$tabindex);
    if (in_array($name, $assignedItems, true)) {
        $checkbox = $checkbox->addAttributes(['checked' => true]);
    }
    echo $checkbox;
    echo Html::label($name)->class('form-check-label');
    echo Html::div()->close();
}

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.update_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
