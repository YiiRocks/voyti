<?php

declare(strict_types=1);

use Yiisoft\Form\Field\ErrorSummary;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\RegistrationForm $model
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-admin-create">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.admin.create_user_title') ?></h2>
    <form action="<?= Html::encode($url->generate('voyti/admin-create')) ?>" method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Text::widget()->name('user[username]')->value($model->username)->label($translator->translate('voyti.view.username_label')) ?>
        <?= \Yiisoft\Form\Field\Email::widget()->name('user[email]')->value($model->email)->label($translator->translate('voyti.view.email_label')) ?>
        <?= \Yiisoft\Form\Field\Password::widget()->name('user[password]')->label($translator->translate('voyti.view.password_label')) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.create_button')) ?>
    </form>
</div>
