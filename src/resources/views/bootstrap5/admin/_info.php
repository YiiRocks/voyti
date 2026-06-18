<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var YiiRocks\Voyti\Entity\User $user
 * @var YiiRocks\Voyti\Entity\Profile $profile
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-admin-info">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><?= Html::encode($user->getUsername()) ?></h2>
        <div>
            <a href="<?= Html::encode($url->generate('voyti/admin-update', ['id' => $user->getId()])) ?>" class="btn btn-outline-secondary btn-sm"><?= $translator->translate('voyti.view.update_link') ?></a>
            <a href="<?= Html::encode($url->generate('voyti/admin-update-profile', ['id' => $user->getId()])) ?>" class="btn btn-outline-secondary btn-sm"><?= $translator->translate('voyti.view.update_profile_link') ?></a>
        </div>
    </div>
    <dl class="row">
        <dt class="col-sm-3"><?= $translator->translate('voyti.view.email_label') ?></dt>
        <dd class="col-sm-9"><?= Html::encode($user->getEmail()) ?></dd>
        <dt class="col-sm-3"><?= $translator->translate('voyti.view.admin.registered_label') ?></dt>
        <dd class="col-sm-9"><?= date('Y-m-d H:i:s', $user->getCreatedAt()) ?></dd>
        <dt class="col-sm-3"><?= $translator->translate('voyti.view.status_header') ?></dt>
        <dd class="col-sm-9">
            <?php if ($user->isBlocked()): ?>
                <span class="badge bg-danger"><?= $translator->translate('voyti.view.status_blocked') ?></span>
            <?php elseif ($user->isConfirmed()): ?>
                <span class="badge bg-success"><?= $translator->translate('voyti.view.status_active') ?></span>
            <?php else: ?>
                <span class="badge bg-warning text-dark"><?= $translator->translate('voyti.view.status_pending') ?></span>
            <?php endif; ?>
        </dd>
        <?php if ($profile): ?>
            <dt class="col-sm-3"><?= $translator->translate('voyti.view.name_label') ?></dt>
            <dd class="col-sm-9"><?= Html::encode($profile->getName() ?? '') ?></dd>
            <dt class="col-sm-3"><?= $translator->translate('voyti.view.bio_label') ?></dt>
            <dd class="col-sm-9"><?= Html::encode($profile->getBio() ?? '') ?></dd>
        <?php endif; ?>
    </dl>
    <div class="mt-3 d-flex gap-2 flex-wrap">
        <?php if (!$user->isConfirmed()): ?>
            <form method="post" action="<?= Html::encode($url->generate('voyti/admin-confirm', ['id' => $user->getId()])) ?>">
                <button type="submit" class="btn btn-success btn-sm"><?= $translator->translate('voyti.view.confirm_button') ?></button>
            </form>
        <?php endif; ?>
        <?php if ($user->isBlocked()): ?>
            <form method="post" action="<?= Html::encode($url->generate('voyti/admin-block', ['id' => $user->getId()])) ?>">
                <button type="submit" class="btn btn-warning btn-sm"><?= $translator->translate('voyti.view.unblock_button') ?></button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= Html::encode($url->generate('voyti/admin-block', ['id' => $user->getId()])) ?>">
                <button type="submit" class="btn btn-warning btn-sm"><?= $translator->translate('voyti.view.block_button') ?></button>
            </form>
        <?php endif; ?>
        <?php if ($config->enablePasswordExpiration): ?>
            <form method="post" action="<?= Html::encode($url->generate('voyti/admin-force-password', ['id' => $user->getId()])) ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm"><?= $translator->translate('voyti.view.force_password_change_button') ?></button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= Html::encode($url->generate('voyti/admin-delete', ['id' => $user->getId()])) ?>" onsubmit="return confirm('<?= $translator->translate('voyti.view.delete_user_confirm') ?>')">
            <button type="submit" class="btn btn-danger btn-sm"><?= $translator->translate('voyti.view.delete_button') ?></button>
        </form>
    </div>
</div>
