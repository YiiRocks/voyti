<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Email;
use Yiisoft\Form\Field\ErrorSummary;
use Yiisoft\Form\Field\Password;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\RegistrationForm $model
 * @var YiiRocks\Voyti\Entity\User $user
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-admin-update">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.admin.update_user_title', ['username' => Html::encode($user->getUsername())]) ?></h2>
    <form method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('user[username]')->value($model->username)->label($translator->translate('voyti.view.username_label')) ?>
        <?= Email::widget()->name('user[email]')->value($model->email)->label($translator->translate('voyti.view.email_label')) ?>
        <?= Password::widget()->name('user[password]')->label($translator->translate('voyti.view.password_keep_label')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.update_button')) ?>
    </form>
</div>
