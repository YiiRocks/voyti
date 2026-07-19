<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use YiiRocks\Voyti\Model\User;

trait UserFactoryTrait
{
    private function createUser(
        string $username = 'testuser',
        string $email = 'test@example.com',
        string $passwordHash = 'hash',
        ?int $createdAt = null,
        ?int $confirmedAt = null,
        ?int $blockedAt = null,
        ?string $lastLoginIp = null,
        bool $authTfEnabled = false,
        ?string $authTfType = null,
        ?string $authTfKey = null,
        bool $gdprConsent = false,
        ?int $gdprConsentDate = null,
    ): User {
        $timestamp = $createdAt ?? time();

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($passwordHash);
        $user->setAuthKey('key');
        $user->setCreatedAt($timestamp);
        $user->setUpdatedAt($timestamp);
        if ($confirmedAt !== null) {
            $user->setConfirmedAt($confirmedAt);
        }
        if ($blockedAt !== null) {
            $user->setBlockedAt($blockedAt);
        }
        if ($lastLoginIp !== null) {
            $user->setLastLoginIp($lastLoginIp);
        }
        $user->setAuthTfEnabled($authTfEnabled);
        $user->setAuthTfType($authTfType);
        $user->setAuthTfKey($authTfKey);
        if ($gdprConsent) {
            $user->setGdprConsent(true);
            $user->setGdprConsentDate($gdprConsentDate);
        }
        $user->save();

        return $user;
    }
}
