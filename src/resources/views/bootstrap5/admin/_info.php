<?php

declare(strict_types=1);
use Yiisoft\FormModel\Field;

use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Entity\User $user
 * @var YiiRocks\Voyti\Entity\Profile $profile
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<div class="voyti-admin-info">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><?= Html::encode($user->getUsername()) ?></h1>
        <div>
            <a href="<?= Html::encode($url->generate('voyti/admin-update', ['id' => $user->getId()])) ?>" class="btn btn-outline-secondary btn-sm"><?= $translator->translate('voyti.view.update_link', category: 'voyti') ?></a>
            <a href="<?= Html::encode($url->generate('voyti/admin-update-profile', ['id' => $user->getId()])) ?>" class="btn btn-outline-secondary btn-sm"><?= $translator->translate('voyti.view.update_profile_link', category: 'voyti') ?></a>
        </div>
    </div>
    <dl class="row">
        <dt class="col-sm-3"><?= $translator->translate('voyti.view.email_label', category: 'voyti') ?></dt>
        <dd class="col-sm-9"><?= Html::encode($user->getEmail()) ?></dd>
        <dt class="col-sm-3"><?= $translator->translate('voyti.view.admin.registered_label', category: 'voyti') ?></dt>
        <dd class="col-sm-9"><?= date('Y-m-d H:i:s', $user->getCreatedAt()) ?></dd>
        <dt class="col-sm-3"><?= $translator->translate('voyti.view.status_header', category: 'voyti') ?></dt>
        <dd class="col-sm-9">
            <?php if ($user->isBlocked()): ?>
                <span class="badge bg-danger"><?= $translator->translate('voyti.view.status_blocked', category: 'voyti') ?></span>
            <?php elseif ($user->isConfirmed()): ?>
                <span class="badge bg-success"><?= $translator->translate('voyti.view.status_active', category: 'voyti') ?></span>
            <?php else: ?>
                <span class="badge bg-warning text-dark"><?= $translator->translate('voyti.view.status_pending', category: 'voyti') ?></span>
            <?php endif; ?>
        </dd>
        <?php if ($profile): ?>
            <dt class="col-sm-3"><?= $translator->translate('voyti.view.name_label', category: 'voyti') ?></dt>
            <dd class="col-sm-9"><?= Html::encode($profile->getName() ?? '') ?></dd>
            <dt class="col-sm-3"><?= $translator->translate('voyti.view.bio_label', category: 'voyti') ?></dt>
            <dd class="col-sm-9"><?= Html::encode($profile->getBio() ?? '') ?></dd>
        <?php endif; ?>
    </dl>
    <div class="mt-3 d-flex gap-2 flex-wrap">
<?php if (!$user->isConfirmed()): ?>
            <?php
            $form = Html::form(
                $url->generate('voyti/admin-confirm', ['id' => $user->getId()]),
                'post'
            );
    ?>
            <?= $form->begin() ?>
                <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.confirm_button', category: 'voyti'))->class('btn', 'btn-success', 'btn-sm')
            )
    ?>
                <?= $form->end() ?>
        <?php endif; ?>
        <?php if ($user->isBlocked()): ?>
            <?php
            $form = Html::form(
                $url->generate('voyti/admin-block', ['id' => $user->getId()]),
                'post'
            );
            ?>
            <?= $form->begin() ?>
                <?= Field::buttonGroup()
                    ->buttons(
                        Button::submit($translator->translate('voyti.view.unblock_button', category: 'voyti'))->class('btn', 'btn-warning', 'btn-sm')
                    )
            ?>
                <?= $form->end() ?>
        <?php else: ?>
            <?php
            $form = Html::form(
                $url->generate('voyti/admin-block', ['id' => $user->getId()]),
                'post'
            );
            ?>
            <?= $form->begin() ?>
                <?= Field::buttonGroup()
                    ->buttons(
                        Button::submit($translator->translate('voyti.view.block_button', category: 'voyti'))->class('btn', 'btn-warning', 'btn-sm')
                    )
            ?>
                <?= $form->end() ?>
        <?php endif; ?>
        <?php if ($config->enablePasswordExpiration): ?>
            <?php
            $form = Html::form(
                $url->generate('voyti/admin-force-password', ['id' => $user->getId()]),
                'post'
            );
            ?>
            <?= $form->begin() ?>
                <?= Field::buttonGroup()
                    ->buttons(
                        Button::submit($translator->translate('voyti.view.force_password_change_button', category: 'voyti'))->class('btn', 'btn-outline-secondary', 'btn-sm')
                    )
            ?>
                <?= $form->end() ?>
        <?php endif; ?>
        <?php
        $form = Html::form(
            $url->generate('voyti/admin-delete', ['id' => $user->getId()]),
            'post',
            ['onsubmit' => "return confirm('{$translator->translate('voyti.view.delete_user_confirm', category: 'voyti')}')"]
        );
?>
        <?= $form->begin() ?>
            <?= Field::buttonGroup()
        ->buttons(
            Button::submit($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-danger', 'btn-sm')
        )
?>
            <?= $form->end() ?>
    </div>
</div>
