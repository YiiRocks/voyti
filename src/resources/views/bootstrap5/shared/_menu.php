<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings')) ?>"><?= $translator->translate('voyti.menu.profile', category: 'voyti') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-account')) ?>"><?= $translator->translate('voyti.menu.account', category: 'voyti') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= Html::encode($url->generate('voyti/settings-networks')) ?>"><?= $translator->translate('voyti.menu.networks', category: 'voyti') ?></a>
    </li>
</ul>
