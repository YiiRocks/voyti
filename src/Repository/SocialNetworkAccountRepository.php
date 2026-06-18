<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\SocialNetworkAccount;

final class SocialNetworkAccountRepository extends BaseRepository
{
    public function findByProviderAndClientId(string $provider, string $clientId): ?SocialNetworkAccount
    {
        return $this->findOne(SocialNetworkAccount::class, ['provider' => $provider, 'client_id' => $clientId]);
    }

    public function findByUserId(int $userId): array
    {
        return $this->findAll(SocialNetworkAccount::class, ['user_id' => $userId]);
    }

    #[\Override]
    public function save(object|array $model): bool
    {
        return parent::save($model);
    }
}
