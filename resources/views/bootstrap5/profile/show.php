<?php

declare(strict_types=1);

use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array{
 *   displayName: string,
 *   gravatarUrl: string|null,
 *   showAdminFields: bool,
 *   email: string|null,
 *   registeredDisplay: string|null,
 *   statusLabel: string|null,
 *   statusBadgeClass: string|null,
 *   publicEmail: string|null,
 *   location: string|null,
 *   website: string|null,
 *   timezone: string|null,
 *   bio: string|null,
 *   profilePreviewClass: string,
 * } $profile
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($profile['displayName']);

/** @psalm-suppress InvalidScope */
echo $this->render('../shared/view_profile', ['profile' => $profile, 'translator' => $translator]);
