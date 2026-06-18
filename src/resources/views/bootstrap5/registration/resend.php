<?php

declare(strict_types=1);

use Yiisoft\Form\Field\Email;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\ResendForm $model
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-resend">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.registration.resend_title') ?></h2>
    <form action="<?= Html::encode($url->generate('voyti/resend')) ?>" method="post" novalidate>
        <?= Email::widget()->name('resend[email]')->value($model->email)->label($translator->translate('voyti.view.email_label')) ?>
        <?= \YiiRocks\Voyti\Helper\RecaptchaHelper::render($model, $config) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.send_button')) ?>
    </form>
</div>
