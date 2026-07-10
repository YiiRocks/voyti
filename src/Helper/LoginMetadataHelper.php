<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;

final class LoginMetadataHelper
{
    /**
     * @param array<array-key, mixed> $serverParams
     */
    public static function recordLogin(User $user, array $serverParams, ModuleConfig $config): void
    {
        $user->setLastLoginAt(time());
        $user->setLastLoginIp($config->disableIpLogging ? '127.0.0.1' : self::remoteAddr($serverParams));
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
}
