<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use YiiRocks\Voyti\Entity\SocialNetworkAccount;
use YiiRocks\Voyti\Repository\SocialNetworkAccountRepository;
use YiiRocks\Voyti\Service\ServiceResult;

final class SocialNetworkAccountConnectService
{
    public function __construct(
        private readonly SocialNetworkAccountRepository $socialNetworkAccountRepository,
    ) {
    }

    public function run(string $provider, string $clientId, array $userAttributes, int $userId): ServiceResult
    {
        $account = $this->socialNetworkAccountRepository->findByProviderAndClientId($provider, $clientId);

        if ($account !== null && $account->getUserId() !== null) {
            return ServiceResult::failure('This account has already been connected to another user');
        }

        if ($account === null) {
            $account = new SocialNetworkAccount();
            $account->setProvider($provider);
            $account->setClientId($clientId);
            $account->setCode(json_encode($userAttributes, JSON_THROW_ON_ERROR));
        }

        $account->setUserId($userId);
        $account->setUsername(null);
        $account->setEmail(null);
        $account->setCode(null);

        if (!$this->socialNetworkAccountRepository->save($account)) {
            return ServiceResult::failure('Unable to connect social network account');
        }

        return ServiceResult::success();
    }
}
