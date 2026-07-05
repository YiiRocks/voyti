<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var UserProfile|null $userProfile
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($user->getUsername());

echo Html::div()->open();
include dirname(__DIR__) . '/shared/_admin-menu.php';

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($user->getUsername());

echo Html::div()->open();
echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/admin-update', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm', 'me-1');
echo Html::a($translator->translate('voyti.view.update_profile_link', category: 'voyti'), $url->generate('voyti/admin-update-profile', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm', 'me-1');
echo Html::a($translator->translate('voyti.view.admin.sessions_link', category: 'voyti'), $url->generate('voyti/admin-session-history', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm');
echo Html::div()->close();
echo Html::div()->close();

$showAdminFields = true;
$profilePreviewClass = 'list-group list-group-flush';
include dirname(__DIR__) . '/shared/view_profile.php';

echo Html::div()->close();
