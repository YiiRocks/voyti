<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Entity\User $user
 * @var YiiRocks\Voyti\Entity\UserProfile $userProfile
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::div()->class('voyti-admin-info')->open();
    echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
        echo Html::H1(Html::encode($user->getUsername()));

        echo Html::div()->open();
            echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/admin-update', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm');
            echo ' ';
            echo Html::a($translator->translate('voyti.view.update_profile_link', category: 'voyti'), $url->generate('voyti/admin-update-userProfile', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm');
        echo Html::div()->close();
    echo Html::div()->close();

    echo '<dl class="row">' . "\n";
    echo '    <dt class="col-sm-3">' . $translator->translate('voyti.view.email_label', category: 'voyti') . '</dt>' . "\n";
    echo '    <dd class="col-sm-9">' . Html::encode($user->getEmail()) . '</dd>' . "\n";
    echo '    <dt class="col-sm-3">' . $translator->translate('voyti.view.admin.registered_label', category: 'voyti') . '</dt>' . "\n";
    echo '    <dd class="col-sm-9">' . date('Y-m-d H:i:s', $user->getCreatedAt()) . '</dd>' . "\n";
    echo '    <dt class="col-sm-3">' . $translator->translate('voyti.view.status_header', category: 'voyti') . '</dt>' . "\n";
    echo '    <dd class="col-sm-9">' . "\n";

    if ($user->isBlocked()) {
        echo '        <span class="badge bg-danger">' . $translator->translate('voyti.view.status_blocked', category: 'voyti') . '</span>' . "\n";
    } elseif ($user->isConfirmed()) {
        echo '        <span class="badge bg-success">' . $translator->translate('voyti.view.status_active', category: 'voyti') . '</span>' . "\n";
    } else {
        echo '        <span class="badge bg-warning text-dark">' . $translator->translate('voyti.view.status_pending', category: 'voyti') . '</span>' . "\n";
    }

    echo '    </dd>' . "\n";

    if ($userProfile) {
        echo '    <dt class="col-sm-3">' . $translator->translate('voyti.view.name_label', category: 'voyti') . '</dt>' . "\n";
        echo '    <dd class="col-sm-9">' . Html::encode($userProfile->getName() ?? '') . '</dd>' . "\n";
        echo '    <dt class="col-sm-3">' . $translator->translate('voyti.view.bio_label', category: 'voyti') . '</dt>' . "\n";
        echo '    <dd class="col-sm-9">' . Html::encode($userProfile->getBio() ?? '') . '</dd>' . "\n";
    }

    echo '</dl>' . "\n";

    echo Html::div()->class('mt-3 d-flex gap-2 flex-wrap')->open();

    if (!$user->isConfirmed()) {
        echo Html::form()
            ->post($url->generate('voyti/admin-confirm', ['id' => $user->getId()]))
            ->csrf($csrf)
            ->open();

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.confirm_button', category: 'voyti'))->class('btn', 'btn-success', 'btn-sm')
            );

        echo Html::form()->close();
    }

    echo Html::form()
        ->post($url->generate('voyti/admin-block', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();

    if ($user->isBlocked()) {
        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.unblock_button', category: 'voyti'))->class('btn', 'btn-warning', 'btn-sm')
            );
    } else {
        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.block_button', category: 'voyti'))->class('btn', 'btn-warning', 'btn-sm')
            );
    }

    echo Html::form()->close();

    if ($config->enablePasswordExpiration) {
        echo Html::form()
            ->post($url->generate('voyti/admin-force-password', ['id' => $user->getId()]))
            ->csrf($csrf)
            ->open();

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.force_password_change_button', category: 'voyti'))->class('btn', 'btn-outline-secondary', 'btn-sm')
            );

        echo Html::form()->close();
    }

    echo Html::form()
        ->post($url->generate('voyti/admin-delete', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-danger', 'btn-sm')
        );

    echo Html::form()->close();

    echo Html::div()->close();
echo Html::div()->close();
