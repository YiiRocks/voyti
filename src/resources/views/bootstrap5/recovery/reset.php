<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\RecoveryForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.recovery.reset_title', category: 'voyti')); ?>
<div class="voyti-reset">
    <h1><?= $translator->translate('voyti.view.recovery.reset_title', category: 'voyti') ?></h1>
    <form method="post" novalidate>
        <?= Field::password($model, 'password') ?>
        <?= Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.recovery.reset_button', category: 'voyti'))
            )
?>
    </form>
</div>
