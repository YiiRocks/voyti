<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Entity\SocialNetworkAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\SocialNetworkAccountRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Auth\IdentityServiceInterface;
use Yiisoft\Session\SessionInterface;

final class SocialNetworkAuthenticateService
{
    public function __construct(
        private readonly ModuleConfig $config,
        private readonly SocialNetworkAccountRepository $socialNetworkAccountRepository,
        private readonly UserRepository $userRepository,
        private readonly IdentityServiceInterface $identityService,
        private readonly SessionInterface $session,
    ) {
    }

    public function run(string $provider, string $clientId, array $userAttributes): ServiceResult
    {
        if (!$this->config->enableSocialNetworkRegistration) {
            return ServiceResult::failure('Social network registration is disabled');
        }

        if ($clientId === '') {
            $oauthData = $this->session->get('oauth_client_data');
            if ($oauthData !== null && is_array($oauthData)) {
                $clientId = (string)($oauthData['user_id'] ?? '');
                $userAttributes = array_merge($oauthData, $userAttributes);
            }
        }

        if ($clientId === '') {
            return ServiceResult::failure('Unable to determine social network client ID');
        }

        $account = $this->socialNetworkAccountRepository->findByProviderAndClientId($provider, $clientId);

        if ($account === null) {
            $account = $this->createAccount($provider, $clientId, $userAttributes);
            if ($account === null) {
                return ServiceResult::failure('Unable to create social network account');
            }
        }

        if ($account->getUserId() !== null) {
            $user = $this->userRepository->findById($account->getUserId());
            if ($user === null) {
                return ServiceResult::failure('Associated user not found');
            }
            if ($user->isBlocked()) {
                return ServiceResult::failure('Your account has been blocked');
            }

            $this->identityService->login($user);
            $user->setLastLoginAt(time());
            $user->setLastLoginIp($this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
            $user->save();

            $this->session->remove('oauth_client_data');

            return ServiceResult::success();
        }

        $this->session->set('social_network_account_id', $account->getId());

        return ServiceResult::success();
    }

    private function createAccount(string $provider, string $clientId, array $attributes): ?SocialNetworkAccount
    {
        $account = new SocialNetworkAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setUsername($attributes['username'] ?? ($attributes['name'] ?? null));
        $account->setEmail($attributes['email'] ?? null);
        $account->setCode(json_encode($attributes, JSON_THROW_ON_ERROR));
        $account->setCreatedAt(time());

        $email = $account->getEmail();
        if ($email !== null) {
            $user = $this->userRepository->findByEmail($email);
            if ($user !== null) {
                $account->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
            }
        }

        if (!$this->socialNetworkAccountRepository->save($account)) {
            return null;
        }

        return $account;
    }
}
