<?php

declare(strict_types=1);

use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Field\Text;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Form\LoginForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-2fa">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.two_factor.title') ?></h2>
    <form action="<?= Html::encode($url->generate('voyti/confirm')) ?>" method="post" novalidate>
        <?= Text::widget()->name('login[twoFactorAuthenticationCode]')->label($translator->translate('voyti.view.two_factor.code_label'))->inputAttributes(['autocomplete' => 'one-time-code']) ?>
        <?= SubmitButton::widget()->label($translator->translate('voyti.view.two_factor.verify_button')) ?>
    </form>
</div>
