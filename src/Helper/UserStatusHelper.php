<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use YiiRocks\Voyti\Model\User;
use Yiisoft\Translator\TranslatorInterface;

final class UserStatusHelper
{
    /**
     * @return array{0: string, 1: string} [label, cssClass]
     */
    public static function labelAndBadgeClass(User $user, TranslatorInterface $translator): array
    {
        return match (true) {
            $user->isBlocked() => [$translator->translate('voyti.view.status_blocked'), 'bg-danger'],
            $user->isConfirmed() => [$translator->translate('voyti.view.status_active'), 'bg-success'],
            default => [$translator->translate('voyti.view.status_pending'), 'bg-warning text-dark'],
        };
    }
}
