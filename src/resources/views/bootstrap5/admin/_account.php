<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\RegistrationForm $model
 * @var YiiRocks\Voyti\Entity\User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.admin.update_user_title', category: 'voyti'));

echo Html::div()->class('voyti-admin-update')->open();
    echo Html::H1($translator->translate('voyti.view.admin.update_user_title', ['username' => Html::encode($user->getUsername())], category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/admin-update', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();

    echo Field::errorSummary(null)->errors($errors);

    echo Field::text($model, 'username')->name('user[username]')->value($model->username);

    echo Field::email($model, 'email')->name('user[email]')->value($model->email);

    echo Field::password($model, 'password')->name('user[password]');

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.update_button', category: 'voyti'))
        );

    echo Html::form()->close();
echo Html::div()->close();
