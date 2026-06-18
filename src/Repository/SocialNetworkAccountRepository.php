<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveRecordFactory;
use YiiRocks\Voyti\Entity\SocialNetworkAccount;

final class SocialNetworkAccountRepository
{
    use RepositoryTrait;

    public function __construct(ActiveRecordFactory $arFactory)
    {
        $this->arFactory = $arFactory;
    }

    public function findByProviderAndClientId(string $provider, string $clientId): ?SocialNetworkAccount
    {
        return $this->findOne(SocialNetworkAccount::class, ['provider' => $provider, 'client_id' => $clientId]);
    }

    public function findByUserId(int $userId): array
    {
        return $this->findAll(SocialNetworkAccount::class, ['user_id' => $userId]);
    }

    public function save(SocialNetworkAccount $account): bool
    {
        return $account->save();
    }
}
