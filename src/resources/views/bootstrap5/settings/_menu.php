<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings')) ?>"><?= $translator->translate('voyti.view.settings.profile') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-account')) ?>"><?= $translator->translate('voyti.view.settings.account') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-networks')) ?>"><?= $translator->translate('voyti.view.settings.networks') ?></a>
    </li>
    <?php if (!empty($config) && $config->enableGdprCompliance): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-privacy')) ?>"><?= $translator->translate('voyti.view.settings.privacy') ?></a>
    </li>
    <?php endif; ?>
</ul>
