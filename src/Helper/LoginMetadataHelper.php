<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use YiiRocks\Voyti\Model\User;

/**
 * Records login metadata (last login time and IP) on a `User`.
 */
final class LoginMetadataHelper
{
    /**
     * @param array<array-key, mixed> $serverParams
     */
    public static function recordLogin(User $user, array $serverParams): void
    {
        $user->setLastLoginAt(time());
        $user->setLastLoginIp(self::remoteAddr($serverParams));
        $user->save();
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    public static function remoteAddr(array $serverParams): string
    {
        /** @var mixed $remoteAddr */
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddr) && $remoteAddr !== '' ? $remoteAddr : '127.0.0.1';
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    public static function userAgent(array $serverParams): ?string
    {
        /** @var mixed $userAgent */
        $userAgent = $serverParams['HTTP_USER_AGENT'] ?? null;

        return is_string($userAgent) && $userAgent !== '' ? $userAgent : null;
    }
}
