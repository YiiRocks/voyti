<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $accounts
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
            echo Html::tag('li', Html::encode($account->getProvider()))->class('list-group-item');
        }

        echo Html::ul()->close();
    }
echo Html::div()->close();
