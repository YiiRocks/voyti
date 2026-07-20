<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\ProfileCardViewData;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var ProfileCardViewData $profile
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($profile->displayName);

/** @psalm-suppress InvalidScope */
echo $this->render('../shared/view_profile', ['profile' => $profile, 'translator' => $translator]);
