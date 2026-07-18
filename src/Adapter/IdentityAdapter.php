<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Adapter;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;

use function time;

/**
 * Bridges `yiisoft/auth`'s identity-repository contracts to {@see User} and {@see UserToken}, resolving
 * identities by ID and by API token (honoring {@see ModuleConfig::$apiTokenLifespan} expiry).
 */
final readonly class IdentityAdapter implements IdentityRepositoryInterface, IdentityWithTokenRepositoryInterface
{
    private \Closure $now;

    public function __construct(
        private ModuleConfig $config,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? static fn(): int => time();
    }

    /**
     * @return User|null
     */
    #[\Override]
    public function findIdentity(string $id): ?IdentityInterface
    {
        return User::findById((int) $id);
    }

    #[\Override]
    public function findIdentityByToken(string $token, ?string $type = null): ?IdentityInterface
    {
        $userToken = UserToken::findByCodeAndType(
            hash('sha256', $token),
            UserToken::TYPE_API_ACCESS,
        );

        if ($userToken === null) {
            return null;
        }

        if (
            $this->config->apiTokenLifespan !== null
            && ((int) ($this->now)() - $userToken->getCreatedAt()) > $this->config->apiTokenLifespan
        ) {
            return null;
        }

        return $userToken->getUser();
    }
}
