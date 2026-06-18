<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Email;
use Yiisoft\Form\Field\ErrorSummary;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\RecoveryForm $model
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var array $errors
 */
?>
<div class="voyti-recovery">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.recovery.request_title') ?></h2>
    <form action="<?= Html::encode($url->generate('voyti/forgot')) ?>" method="post" novalidate>
        <?= ErrorSummary::widget()->errors($errors) ?>
        <?= Email::widget()->name('recovery[email]')->value($model->email)->label($translator->translate('voyti.view.email_label')) ?>
        <?= \YiiRocks\Voyti\Helper\RecaptchaHelper::render($model, $config) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.recovery.send_link_button')) ?>
        <p class="mt-3"><a href="<?= Html::encode($url->generate('voyti/login')) ?>"><?= $translator->translate('voyti.view.recovery.back_to_login') ?></a></p>
    </form>
</div>
