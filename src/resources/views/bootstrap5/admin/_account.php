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
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.admin.update_user_title', category: 'voyti')); ?>
<div class="voyti-admin-update">
    <h1><?= $translator->translate('voyti.view.admin.update_user_title', ['username' => Html::encode($user->getUsername())], category: 'voyti') ?></h1>
<?php
$form = Html::form(
    $url->generate('voyti/admin-update', ['id' => $user->getId()]),
    'post',
    ['novalidate' => true]
);
?>
<?= $form->begin() ?>
    <?= Field::errorSummary(null)->errors($errors) ?>
    <?= Field::text($model, 'username')->name('user[username]')->value($model->username) ?>
    <?= Field::email($model, 'email')->name('user[email]')->value($model->email) ?>
    <?= Field::password($model, 'password')->name('user[password]') ?>
    <?= Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.update_button', category: 'voyti'))
        )
?>
<?= $form->end() ?>
    </form>
</div>
