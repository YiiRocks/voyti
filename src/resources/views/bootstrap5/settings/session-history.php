<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var ModuleConfig $config
 * @var array $sessions
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-session-history">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.session_history.title', category: 'voyti') ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= $translator->translate('voyti.view.session_history.ip', category: 'voyti') ?></th>
                    <th><?= $translator->translate('voyti.view.session_history.user_agent', category: 'voyti') ?></th>
                    <th><?= $translator->translate('voyti.view.session_history.created', category: 'voyti') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?= Html::encode($session->getIp() ?? '') ?></td>
                        <td><?= Html::encode($session->getUserAgent() ?? '') ?></td>
                        <td><?= date('Y-m-d H:i:s', $session->getCreatedAt()) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
