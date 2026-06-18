<?php

declare(strict_types=1);
use Yiisoft\FormModel\Field;

use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Settings\SettingsForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var YiiRocks\Voyti\Entity\User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.account.title', category: 'voyti')); ?>
<div class="voyti-account">
    <?php include dirname(__DIR__) . '/shared/_menu.php'; ?>
    <h1><?= $translator->translate('voyti.view.account.title', category: 'voyti') ?></h1>
    <form method="post" novalidate>
        <?= Field::errorSummary(null)->errors($errors) ?>
        <?= Field::text($model, 'username') ?>
        <?= Field::email($model, 'email') ?>
        <?= Field::password($model, 'password') ?>
        <?= Field::password($model, 'currentPassword') ?>
        <?php if ($config->enableTwoFactorAuth): ?>
            <fieldset>
                <legend class="h5"><?= $translator->translate('voyti.view.account.two_factor_title', category: 'voyti') ?></legend>
                <?php if ($user->isAuthTfEnabled()): ?>
                    <p><?= $translator->translate('voyti.view.account.two_factor_enabled', category: 'voyti') ?></p>
                    <?= Field::checkbox($model, 'authTfEnabled')->inputValue('0')->label($translator->translate('voyti.view.account.disable_two_factor', category: 'voyti')) ?>
                <?php else: ?>
                    <?= Field::checkbox($model, 'authTfEnabled')->inputValue('1')->label($translator->translate('voyti.view.account.enable_two_factor', category: 'voyti')) ?>
                <?php endif; ?>
            </fieldset>
        <?php endif; ?>
        <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.save_button', category: 'voyti'))
            )
?>
    </form>
</div>
