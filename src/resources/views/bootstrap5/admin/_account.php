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
    <h2 class="mb-4"><?= $translator->translate('voyti.view.admin.update_user_title', ['username' => Html::encode($user->getUsername())], category: 'voyti') ?></h2>
    <form method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('user[username]')->value($model->username)->label($translator->translate('voyti.view.username_label', category: 'voyti')) ?>
        <?= Email::widget()->name('user[email]')->value($model->email)->label($translator->translate('voyti.view.email_label', category: 'voyti')) ?>
        <?= Password::widget()->name('user[password]')->label($translator->translate('voyti.view.password_keep_label', category: 'voyti')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.update_button', category: 'voyti')) ?>
    </form>
</div>
