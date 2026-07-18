<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Service\ServiceResult;

/**
 * Links a social provider identity to an already-authenticated user, creating the
 * {@see UserSocialAccount} row if it doesn't exist yet and failing if it's already connected to
 * a different user.
 */
final readonly class UserSocialAccountConnectService
{
    public function run(string $provider, string $clientId, array $userAttributes, int $userId): ServiceResult
    {
        $account = UserSocialAccount::findByProviderAndClientId($provider, $clientId);

        if ($account !== null && $account->getUserId() !== null) {
            return ServiceResult::failure('This account has already been connected to another user');
        }

        if ($account === null) {
            $account = new UserSocialAccount();
            $account->setProvider($provider);
            $account->setClientId($clientId);
            $account->setData(json_encode($userAttributes, JSON_THROW_ON_ERROR));
            $account->setCreatedAt(time());
        }

        $account->setUserId($userId);
        $account->setUsername(null);
        $account->setEmail(null);
        $account->setCode(null);

        $account->save();

        return ServiceResult::success();
    }
}
