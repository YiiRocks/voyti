<?php

declare(strict_types=1);

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var list<UserSocialAccount> $accounts
 * @var AuthClientRegistry $authClients
 * @var list<string> $excludedProviders
 * @var string $connectRouteName
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var string $csrf
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.networks.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['config' => $config, 'url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.networks.title', category: 'voyti'));

if (empty($accounts)) {
    echo Html::p($translator->translate('voyti.view.networks.no_networks', category: 'voyti'));
} else {
    echo Html::ul()->class('list-group')->open();

    foreach ($accounts as $account) {
        $disconnect = Html::form()
            ->post($url->generate('voyti/social-network-delete', ['id' => $account->getId() ?? 0]))
            ->csrf($csrf)
            ->open()
            . Field::buttonGroup()
                ->buttons(
                    Html::submitButton($translator->translate('voyti.view.disconnect_button', category: 'voyti'))
                        ->class('btn', 'btn-outline-danger', 'btn-sm')
                        ->attribute('tabindex', 1),
                )
                ->render()
            . Html::form()->close();

        $providerTitle = $authClients->getTitle($account->getProvider());

        $content = Html::div()->class('d-flex justify-content-between align-items-center gap-3')->open();
        $content .= Html::span($providerTitle)->render();
        $content .= $disconnect;
        $content .= Html::div()->close();

        echo Html::li($content, ['class' => 'list-group-item'])->encode(false);
    }

    echo Html::ul()->close();
}

if ($authClients->all() !== []) {
    echo Html::div()->class('mt-4')->open();
    $routeName = $connectRouteName;
    /** @psalm-suppress InvalidScope */
    echo $this->render('../shared/_connect', [
        'authClients' => $authClients,
        'excludedProviders' => $excludedProviders,
        'routeName' => $routeName,
        'url' => $url,
    ]);
    echo Html::div()->close();
}

echo Html::div()->close();
