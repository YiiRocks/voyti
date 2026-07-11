<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\ServiceResult;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

final readonly class UserSocialAuthenticateService
{
    private const string SESSION_KEY = 'oauth_client_data';

    public function __construct(
        private ModuleConfig $config,
        private CurrentUser $currentUser,
        private SessionInterface $session,
        private EventDispatcherInterface $eventDispatcher,
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
            /** @var mixed $oauthData */
            $oauthData = $this->session->get(self::SESSION_KEY);
            if ($oauthData !== null && is_array($oauthData)) {
                $clientId = (string)($oauthData['user_id'] ?? '');
                $userAttributes = array_merge($oauthData, $userAttributes);
            }
        }

        if ($clientId === '') {
            return ServiceResult::failure('Unable to determine social network client ID');
        }

        $account = UserSocialAccount::findByProviderAndClientId($provider, $clientId);

        if ($account === null) {
            $account = $this->createAccount($provider, $clientId, $userAttributes);
        }

        if ($account->getUserId() !== null) {
            $user = User::findById($account->getUserId());
            if ($user === null) {
                return ServiceResult::failure('Associated user not found');
            }
            if ($user->isBlocked()) {
                return ServiceResult::failure('Your account has been blocked');
            }

            $this->currentUser->login($user);
            LoginMetadataHelper::recordLogin($user, $serverParams, $this->config);
            $this->eventDispatcher->dispatch(new AfterLoginEvent($user));

            $this->session->remove(self::SESSION_KEY);

            return ServiceResult::success();
        }

        $code = $account->getCode();
        if ($code === null || $code === '') {
            return ServiceResult::failure('Unable to prepare the social account connection');
        }

        $this->session->set(PendingSocialAccountService::SESSION_KEY, $code);

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
            $user = User::findByEmail($email);
            if ($user !== null) {
                $account->setUserId((int) $user->getId());
            }
        }

        $account->save();

        if ($account->getUserId() !== null) {
            $user = User::findById($account->getUserId());
            if ($user !== null) {
                $account->connect($user);
            }
        }

        return $account;
    }

    /**
     * @param array<array-key, mixed> $attributes
     *
     * @return null|string
     */
    private function stringAttribute(array $attributes, string $key): string|null
    {
        /** @var mixed $value */
        $value = $attributes[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
