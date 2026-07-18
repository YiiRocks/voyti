<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\Security\Random;

/**
 * Issues and revokes API bearer tokens ({@see UserToken::TYPE_API_ACCESS}) for a user; only the
 * SHA-256 hash of the raw token is persisted.
 */
final readonly class ApiTokenService
{
    public function generate(User $user): string
    {
        $rawToken = Random::string(64);

        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setType(UserToken::TYPE_API_ACCESS);
        $userToken->setCode(hash('sha256', $rawToken));
        $userToken->setCreatedAt(time());
        $userToken->save();

        return $rawToken;
    }

    public function revokeAll(User $user): int
    {
        $tokens = array_filter(
            UserToken::findByUserId((int) $user->getId()),
            static fn(UserToken $token): bool => $token->getType() === UserToken::TYPE_API_ACCESS,
        );

        foreach ($tokens as $token) {
            $token->delete();
        }

        return count($tokens);
    }
}
