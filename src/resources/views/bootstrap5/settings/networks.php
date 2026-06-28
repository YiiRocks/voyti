<?php

declare(strict_types=1);

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use YiiRocks\Voyti\Entity\UserSocialAccount;

/**
 * @var list<UserSocialAccount> $accounts
 * @var AuthClientRegistry $authClients
 * @var list<string> $excludedProviders
 * @var string $connectRouteName
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

$this->setTitle($translator->translate('voyti.view.networks.title', category: 'voyti'));

echo Html::div()->class('voyti-networks')->open();
include dirname(__DIR__) . '/shared/_menu.php';

echo Html::H1($translator->translate('voyti.view.networks.title', category: 'voyti'));

if (empty($accounts)) {
    echo Html::p($translator->translate('voyti.view.networks.no_networks', category: 'voyti'));
} else {
    echo Html::ul()->class('list-group')->open();

    foreach ($accounts as $account) {
        $disconnect = Html::form()
            ->post($url->generate('voyti/settings-disconnect', ['id' => $account->getId() ?? 0]))
            ->csrf($csrf)
            ->open()
            . Html::submitButton($translator->translate('voyti.view.disconnect_button', category: 'voyti'))
                ->class('btn', 'btn-outline-danger', 'btn-sm')
            . Html::form()->close();

        $content = Html::div()->class('d-flex justify-content-between align-items-center gap-3')->open();
        $content .= Html::span($account->getProvider());
        $content .= $disconnect;
        $content .= Html::div()->close();

        echo Html::li($content, ['class' => 'list-group-item'])->encode(false);
    }

    echo Html::ul()->close();
}

if ($authClients->all() !== []) {
    echo Html::div()->class('mt-4')->open();
    $routeName = $connectRouteName;
    include dirname(__DIR__) . '/shared/_connect.php';
    echo Html::div()->close();
}

echo Html::div()->close();
