<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var \YiiRocks\Voyti\Entity\User $user
 * @var \YiiRocks\Voyti\ModuleConfig $config
 * @var array $sessions
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-session-history">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.session_history.title') ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= $translator->translate('voyti.view.session_history.ip') ?></th>
                    <th><?= $translator->translate('voyti.view.session_history.user_agent') ?></th>
                    <th><?= $translator->translate('voyti.view.session_history.created') ?></th>
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
