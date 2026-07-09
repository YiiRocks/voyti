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
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($user->getUsername());

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);

echo Html::H1($user->getUsername());

/** @psalm-suppress InvalidScope */
echo $this->render('../shared/view_profile', [
    'user' => $user,
    'userProfile' => $userProfile,
    'translator' => $translator,
    'showAdminFields' => true,
    'profilePreviewClass' => 'list-group list-group-flush',
]);

echo Html::div()->close();
