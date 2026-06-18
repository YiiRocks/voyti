<?php

declare(strict_types=1);
use Yiisoft\FormModel\Field;

use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Settings\GdprDeleteForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.gdpr.consent_title', category: 'voyti')); ?>
<div class="voyti-gdpr-consent">
    <h1><?= $translator->translate('voyti.view.gdpr.consent_title', category: 'voyti') ?></h1>
    <form method="post" novalidate>
        <?= Field::checkbox($model, 'consent')->inputValue('1') ?>
        <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.save_button', category: 'voyti'))
            )
?>
    </form>
</div>
