<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Service\ServiceResult;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

final class UserSocialAuthenticateService
{
    public function __construct(
        private readonly ModuleConfig $config,
        private readonly UserSocialAccountRepository $userSocialAccountRepository,
        private readonly UserRepository $userRepository,
        private readonly CurrentUser $currentUser,
        private readonly SessionInterface $session,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array<array-key, mixed> $userAttributes
     * @param array<array-key, mixed> $serverParams
     */
    public function run(string $provider, string $clientId, array $userAttributes, array $serverParams = []): ServiceResult
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

        $account = $this->userSocialAccountRepository->findByProviderAndClientId($provider, $clientId);

        if ($account === null) {
            $account = $this->createAccount($provider, $clientId, $userAttributes);
        }

        if ($account->getUserId() !== null) {
            $user = $this->userRepository->findById($account->getUserId());
            if ($user === null) {
                return ServiceResult::failure('Associated user not found');
            }
            if ($user->isBlocked()) {
                return ServiceResult::failure('Your account has been blocked');
            }

            $this->currentUser->login($user);
            $this->updateLastLoginMetadata($user, $serverParams);
            $this->eventDispatcher->dispatch(new AfterLoginEvent($user));

            $this->session->remove('oauth_client_data');

            return ServiceResult::success();
        }

        $code = $account->getCode();
        if ($code === null || $code === '') {
            return ServiceResult::failure('Unable to prepare the social account connection');
        }

        $this->session->set('social_network_account_code', $code);

        return ServiceResult::success();
    }

    /**
     * @param array $attributes
     */
    private function createAccount(string $provider, string $clientId, array $attributes): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $username = $this->stringAttribute($attributes, 'username') ?? $this->stringAttribute($attributes, 'name');
        $email = $this->stringAttribute($attributes, 'email');
        $account->setUsername($username);
        $account->setEmail($email);
        $account->setCode(Random::string(32));
        $account->setData(json_encode($attributes, JSON_THROW_ON_ERROR));
        $account->setCreatedAt(time());

        $email = $account->getEmail();
        if ($email !== null) {
            $user = $this->userRepository->findByEmail($email);
            if ($user !== null) {
                $account->setUserId((int) $user->getId());
            }
        }

        $this->userSocialAccountRepository->save($account);

        if ($account->getUserId() !== null) {
            $user = $this->userRepository->findById($account->getUserId());
            if ($user !== null) {
                $account->connect($user);
            }
        }

        return $account;
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    private function remoteAddr(array $serverParams): string
    {
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddr) && $remoteAddr !== '' ? $remoteAddr : '127.0.0.1';
    }

    /**
     * @param array<array-key, mixed> $attributes
     *
     * @return null|string
     */
    private function stringAttribute(array $attributes, string $key): string|null
    {
        $value = $attributes[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    private function updateLastLoginMetadata(User $user, array $serverParams): void
    {
        $user->setLastLoginAt(time());
        $user->setLastLoginIp($this->config->disableIpLogging ? '127.0.0.1' : $this->remoteAddr($serverParams));
        $user->save();
    }
}
