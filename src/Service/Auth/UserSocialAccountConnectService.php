<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Service\ServiceResult;

final class UserSocialAccountConnectService
{
    public function __construct(
        private readonly UserSocialAccountRepository $userSocialAccountRepository,
    ) {
    }

    public function run(string $provider, string $clientId, array $userAttributes, int $userId): ServiceResult
    {
        $account = $this->userSocialAccountRepository->findByProviderAndClientId($provider, $clientId);

        if ($account !== null && $account->getUserId() !== null) {
            return ServiceResult::failure('This account has already been connected to another user');
        }

        if ($account === null) {
            $account = new UserSocialAccount();
            $account->setProvider($provider);
            $account->setClientId($clientId);
            $account->setCode(json_encode($userAttributes, JSON_THROW_ON_ERROR));
        }

        $account->setUserId($userId);
        $account->setUsername(null);
        $account->setEmail(null);
        $account->setCode(null);

        if (!$this->userSocialAccountRepository->save($account)) {
            return ServiceResult::failure('Unable to connect social network account');
        }

        return ServiceResult::success();
    }
}
