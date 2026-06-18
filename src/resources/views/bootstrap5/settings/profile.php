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
 * @var YiiRocks\Voyti\Entity\Profile $profile
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.settings.title', category: 'voyti')); ?>
<div class="voyti-settings">
    <?php include dirname(__DIR__) . '/shared/_menu.php'; ?>
    <h1><?= $translator->translate('voyti.view.settings.title', category: 'voyti') ?></h1>
    <form method="post" novalidate>
        <?= Field::errorSummary(null)->errors($errors) ?>
        <?= Field::text($model, 'name') ?>
        <?= Field::email($model, 'publicEmail') ?>
        <?= Field::textarea($model, 'bio') ?>
        <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.save_button', category: 'voyti'))
            )
?>
    </form>
</div>
