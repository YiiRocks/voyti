<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserSocialAccount;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class UserSocialAccountRepository
{
    public function findByCode(string $code): ?UserSocialAccount
    {
        /** @var ?UserSocialAccount $account */
        $account = UserSocialAccount::query()->where(['code' => $code])->one();
        return $account;
    }

    public function findByProviderAndClientId(string $provider, string $clientId): ?UserSocialAccount
    {
        /** @var ?UserSocialAccount $account */
        $account = UserSocialAccount::query()->where(['provider' => $provider, 'client_id' => $clientId])->one();
        return $account;
    }

    /**
     * @psalm-return list<UserSocialAccount>
     */
    public function findByUserId(int $userId): array
    {
        /** @var list<UserSocialAccount> $accounts */
        $accounts = UserSocialAccount::query()->where(['user_id' => $userId])->all();
        return $accounts;
    }

    public function save(ActiveRecordInterface $model): void
    {
        $model->save();
    }
}
