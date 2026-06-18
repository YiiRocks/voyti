<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings')) ?>"><?= $translator->translate('voyti.view.settings.profile', category: 'voyti') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-account')) ?>"><?= $translator->translate('voyti.view.settings.account', category: 'voyti') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-networks')) ?>"><?= $translator->translate('voyti.view.settings.networks', category: 'voyti') ?></a>
    </li>
    <?php if (!empty($config) && $config->enableGdprCompliance): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-privacy')) ?>"><?= $translator->translate('voyti.view.settings.privacy', category: 'voyti') ?></a>
    </li>
    <?php endif; ?>
</ul>
