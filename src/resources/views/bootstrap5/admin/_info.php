<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var UserProfile|null $userProfile
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_admin-menu.php';

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($user->getUsername());

echo Html::div()->open();
echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/admin-update', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm');
echo ' ';
echo Html::a($translator->translate('voyti.view.update_profile_link', category: 'voyti'), $url->generate('voyti/admin-update-profile', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm');
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
    echo Html::span($translator->translate('voyti.view.status_blocked', category: 'voyti'))->class('badge', 'bg-danger');
} elseif ($user->isConfirmed()) {
    echo Html::span($translator->translate('voyti.view.status_active', category: 'voyti'))->class('badge', 'bg-success');
} else {
    echo Html::span($translator->translate('voyti.view.status_pending', category: 'voyti'))->class('badge', 'bg-warning text-dark');
}

echo '    </dd>' . "\n";

if ($userProfile) {
    echo '    <dt class="col-sm-3">' . $translator->translate('voyti.view.name_label', category: 'voyti') . '</dt>' . "\n";
    echo '    <dd class="col-sm-9">' . Html::encode($userProfile->getName() ?? '') . '</dd>' . "\n";
    echo '    <dt class="col-sm-3">' . $translator->translate('voyti.view.bio_label', category: 'voyti') . '</dt>' . "\n";
    echo '    <dd class="col-sm-9">' . Html::encode($userProfile->getBio() ?? '') . '</dd>' . "\n";
}

echo '</dl>' . "\n";

echo Html::div()->close();
