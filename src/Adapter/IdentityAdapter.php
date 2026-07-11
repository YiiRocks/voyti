<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Adapter;

use YiiRocks\Voyti\Helper\ApiTokenHasher;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;

use function time;

final readonly class IdentityAdapter implements IdentityRepositoryInterface, IdentityWithTokenRepositoryInterface
{
    private \Closure $now;

    public function __construct(
        private ModuleConfig $config,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): int => time();
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
            ApiTokenHasher::hash($token),
            UserToken::TYPE_API_ACCESS,
        );

        if ($userToken === null) {
            return null;
        }

        /** @infection-ignore-all CastInt: the $now closure's declared `int` return type is enforced by PHP at runtime, so the cast never changes the value — it exists only to satisfy Psalm's inference of invoked Closure return types. */
        if (
            $this->config->apiTokenLifespan !== null
            && ((int) ($this->now)() - $userToken->getCreatedAt()) > $this->config->apiTokenLifespan
        ) {
            return null;
        }

        return $userToken->getUser();
    }
}
