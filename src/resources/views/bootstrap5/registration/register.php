<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\RegistrationForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.registration.register_title', category: 'voyti')); ?>
<div class="voyti-register">
    <h1><?= $translator->translate('voyti.view.registration.register_title', category: 'voyti') ?></h1>
    <form action="<?= Html::encode($url->generate('voyti/register')) ?>" method="post" novalidate>
        <?= Field::errorSummary(null)->errors($errors) ?>
        <?= Field::text($model, 'username') ?>
        <?= Field::email($model, 'email') ?>
        <?= Field::password($model, 'password') ?>
        <?php if ($config->enableGdprCompliance): ?>
            <?= Field::checkbox($model, 'gdprConsent')->inputValue('1') ?>
        <?php endif; ?>
        <?= RecaptchaHelper::render($model, $config) ?>
        <?= Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.registration.register_button', category: 'voyti'))
            )
?>
        <p class="mt-3"><a href="<?= Html::encode($url->generate('voyti/login')) ?>"><?= $translator->translate('voyti.view.registration.already_have_account', category: 'voyti') ?></a></p>
    </form>
</div>
