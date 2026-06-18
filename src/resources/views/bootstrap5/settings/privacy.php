<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-privacy">
    <h2 class="mb-4"><?= $translator->translate('voyti.view.privacy.title') ?></h2>
    <div class="list-group">
        <a href="<?= Html::encode($url->generate('voyti/gdpr-consent')) ?>" class="list-group-item list-group-item-action"><?= $translator->translate('voyti.view.privacy.manage_gdpr_consent') ?></a>
        <a href="<?= Html::encode($url->generate('voyti/gdpr-delete')) ?>" class="list-group-item list-group-item-action"><?= $translator->translate('voyti.view.privacy.delete_data') ?></a>
    </div>
</div>
