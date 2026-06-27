<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserSocialAccount;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/** @extends BaseRepository<UserSocialAccount> */
final class UserSocialAccountRepository extends BaseRepository
{
    public function findByCode(string $code): ?UserSocialAccount
    {
        /** @var ?UserSocialAccount $account */
        $account = $this->findOne(UserSocialAccount::class, ['code' => $code]);
        return $account;
    }

    public function findByProviderAndClientId(string $provider, string $clientId): ?UserSocialAccount
    {
        /** @var ?UserSocialAccount $account */
        $account = $this->findOne(UserSocialAccount::class, ['provider' => $provider, 'client_id' => $clientId]);
        return $account;
    }

    /**
     * @psalm-return list<UserSocialAccount>
     */
    public function findByUserId(int $userId): array
    {
        return $this->findAll(UserSocialAccount::class, ['user_id' => $userId]);
    }

    #[\Override]
    public function save(ActiveRecordInterface $model): void
    {
        parent::save($model);
    }
}
