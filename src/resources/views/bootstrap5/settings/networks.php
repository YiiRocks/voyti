<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array $accounts
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-networks">
    <?php include dirname(__DIR__) . '/shared/_menu.php'; ?>
    <h2 class="mb-4"><?= $translator->translate('voyti.view.networks.title', category: 'voyti') ?></h2>
    <?php if (empty($accounts)): ?>
        <p><?= $translator->translate('voyti.view.networks.no_networks', category: 'voyti') ?></p>
    <?php else: ?>
        <ul class="list-group">
            <?php foreach ($accounts as $account): ?>
                <li class="list-group-item"><?= Html::encode($account->getProvider()) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
