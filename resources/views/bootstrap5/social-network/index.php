<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use YiiRocks\Voyti\ViewData\SocialNetwork\IndexViewData;
use YiiRocks\Voyti\ViewData\SocialNetwork\SocialAccountRow;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.networks.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.networks.title'));

if (empty($data->accounts)) {
    echo Html::p($translator->translate('voyti.view.networks.no_networks'));
} else {
    echo Html::ul()->class('list-group')->open();

    foreach ($data->accounts as $account) {
        /** @var SocialAccountRow $account */
        $disconnect = Html::form()
            ->post($account->formSubmitUrl)
            ->csrf($csrf)
            ->open()
            . Field::buttonGroup()
                ->buttonsData([
                    [$translator->translate('voyti.view.disconnect_button'), 'type' => 'submit', 'class' => 'btn btn-outline-danger btn-sm', 'tabindex' => 1],
                ])
                ->render()
            . Html::form()->close();

        $content = Html::div()->class('d-flex justify-content-between align-items-center gap-3')->open();
        $content .= Html::span($account->providerTitle)->render();
        $content .= $disconnect;
        $content .= Html::div()->close();

        echo Html::li($content, ['class' => 'list-group-item'])->encode(false);
    }

    echo Html::ul()->close();
}

if ($data->authChoice !== null && $data->authChoice->getClients() !== []) {
    echo Html::div()->class('text-center mt-4')->open();
    echo $data->authChoice->render();
    echo Html::div()->close();
}

echo Html::div()->close();
