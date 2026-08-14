<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Adapter;

use Override;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;

/**
 * Bridges `yiisoft/auth`'s identity-repository contract to {@see User}, resolving identities by ID
 * (session/remember-me lookups). API-token resolution lives in the `yiirocks/voyti-api` package,
 * which extends this adapter to add expiry enforcement.
 *
 * @psalm-suppress ClassMustBeFinal — intentionally non-final: it is extended by
 * `yiirocks/voyti-api`'s `ApiTokenIdentityAdapter`, which is not installed when this package's own
 * Psalm run happens.
 */
readonly class IdentityAdapter implements IdentityRepositoryInterface
{
    /**
     * @return User|null
     */
    #[Override]
    public function findIdentity(string $id): ?IdentityInterface
    {
        return User::findById((int) $id);
    }
}
