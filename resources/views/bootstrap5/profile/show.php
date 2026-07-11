<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var UserProfile $userProfile
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($userProfile->getName() ?? $user->getUsername());

/** @psalm-suppress InvalidScope */
echo $this->render('../shared/view_profile', ['user' => $user, 'userProfile' => $userProfile, 'translator' => $translator]);
