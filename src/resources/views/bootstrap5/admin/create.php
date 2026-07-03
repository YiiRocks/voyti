<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var RegistrationForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array<string, list<string>> $errors
 * @var string $csrf
 * @var array<string, Permission|Role> $allItems
 * @var list<string> $assignedItems
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.create_user_title', category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_admin-menu.php';

echo Html::H1($translator->translate('voyti.view.admin.create_user_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/admin-create'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary(null)->errors($errors);

echo Field::text($model, 'username')->name('user[username]')->value($model->username);

echo Field::email($model, 'email')->name('user[email]')->value($model->email);

echo Field::password($model, 'password')->name('user[password]');

echo Html::h3($translator->translate('voyti.view.assignments.title', category: 'voyti'))->class('mb-3');

foreach ($allItems as $name => $item) {
    echo Html::div()->class('form-check')->open();
    $checkbox = Html::input('checkbox')->class('form-check-input')->name('assignedItems[]')->value($name);
    if (in_array($name, $assignedItems, true)) {
        $checkbox = $checkbox->addAttributes(['checked' => true]);
    }
    echo $checkbox;
    echo Html::label($name)->class('form-check-label');
    echo Html::div()->close();
}

echo Field::buttonGroup()
->buttons(
    Html::submitButton($translator->translate('voyti.view.create_button', category: 'voyti'))
);

echo Html::form()->close();
echo Html::div()->close();
