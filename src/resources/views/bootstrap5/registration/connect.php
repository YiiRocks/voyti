<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\SocialNetworkAccount;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var SocialNetworkAccount $account
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-registration-connect">
    <h1><?= $translator->translate('voyti.view.registration.connect_title', category: 'voyti') ?></h1>
    <p><?= $translator->translate('voyti.view.registration.connect_message', category: 'voyti') ?></p>
    <a href="<?= Html::encode($url->generate('voyti/login')) ?>" class="btn btn-primary">
        <?= $translator->translate('voyti.view.registration.connect_login', category: 'voyti') ?>
    </a>
    <a href="<?= Html::encode($url->generate('voyti/register')) ?>" class="btn btn-outline-secondary">
        <?= $translator->translate('voyti.view.registration.connect_register', category: 'voyti') ?>
    </a>
</div>
