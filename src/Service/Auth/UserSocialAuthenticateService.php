<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use Yiisoft\Json\Json;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

/**
 * Handles social login: looks up or creates the {@see UserSocialAccount} for a provider callback,
 * logging in an already-connected user, auto-registering a new one when enabled and the email is
 * free, or otherwise deferring to {@see PendingSocialAccountService} until the account is
 * connected manually.
 */
final readonly class UserSocialAuthenticateService
{
    private const string SESSION_KEY = 'oauth_client_data';

    public function __construct(
        private ModuleConfig $config,
        private CurrentUser $currentUser,
        private SessionInterface $session,
        private EventDispatcherInterface $eventDispatcher,
        private UserCreationHelper $userCreationHelper,
        private PendingSocialAccountService $pendingSocialAccountService,
    ) {}

    /**
     * @param array<array-key, mixed> $userAttributes
     * @param array<array-key, mixed> $serverParams
     */
    public function run(
        string $provider,
        string $clientId,
        array $userAttributes,
        array $serverParams = [],
    ): ServiceResult {
        if (!$this->config->enableSocialNetworkRegistration) {
            return ServiceResult::failure('Social network registration is disabled');
        }

        if ($clientId === '') {
            /** @var mixed $oauthData */
            $oauthData = $this->session->get(self::SESSION_KEY);
            if ($oauthData !== null && is_array($oauthData)) {
                $clientId = (string) ($oauthData['user_id'] ?? '');
                $userAttributes = array_merge($oauthData, $userAttributes);
            }
        }

        if ($clientId === '') {
            return ServiceResult::failure('Unable to determine social network client ID');
        }

        $account = UserSocialAccount::findByProviderAndClientId($provider, $clientId);

        if ($account === null) {
            $account = $this->createAccount($provider, $clientId, $userAttributes, $serverParams);
        }

        if ($account->getUserId() !== null) {
            $user = User::findById($account->getUserId());
            if ($user === null) {
                return ServiceResult::failure('Associated user not found');
            }
            if ($user->isBlocked()) {
                return ServiceResult::failure('Your account has been blocked');
            }

            $previousSessionId = $this->session->getId();
            $this->currentUser->login($user);
            LoginMetadataHelper::recordLogin($user, $serverParams);
            $this->eventDispatcher->dispatch(
                new AfterLoginEvent($user, previousSessionId: $previousSessionId, serverParams: $serverParams),
            );

            $this->session->remove(self::SESSION_KEY);

            return ServiceResult::success();
        }

        $code = $account->getCode();
        if ($code === null || $code === '') {
            return ServiceResult::failure('Unable to prepare the social account connection');
        }

        $this->pendingSocialAccountService->remember($account);

        return ServiceResult::success();
    }

    private function buildUniqueUsername(?string $usernameHint, string $email): string
    {
        $base = $this->sanitizeUsername($usernameHint) ?? $this->sanitizeUsername(explode('@', $email, 2)[0]) ?? 'user';

        $username = $base;
        $suffix = 2;
        while (User::findByUsername($username) !== null) {
            $username = $base . '_' . $suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * @param array $attributes
     * @param array<array-key, mixed> $serverParams
     */
    private function createAccount(
        string $provider,
        string $clientId,
        array $attributes,
        array $serverParams,
    ): UserSocialAccount {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $username = $this->stringAttribute($attributes, 'username') ?? $this->stringAttribute($attributes, 'name');
        $email = $this->stringAttribute($attributes, 'email');
        $account->setUsername($username);
        $account->setEmail($email);
        $account->setCode(Random::string(32));
        $account->setData(Json::encode($attributes));
        $account->setCreatedAt(time());

        $email = $account->getEmail();
        if ($email !== null && $this->config->enableRegistration && User::findByEmail($email) === null) {
            $user = $this->registerUser($email, $account->getUsername(), $serverParams);
            $account->setUserId((int) $user->getId());
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
     * @param array<array-key, mixed> $serverParams
     */
    private function registerUser(string $email, ?string $usernameHint, array $serverParams): User
    {
        $username = $this->buildUniqueUsername($usernameHint, $email);
        $password = Random::string(24);

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        $user->setRegistrationIp(LoginMetadataHelper::remoteAddr($serverParams));

        $this->userCreationHelper->persistAndNotifySkippingConfirmation($user, $password);

        return $user;
    }

    private function sanitizeUsername(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = (string) preg_replace('/[^-a-zA-Z0-9_.@]/', '', $value);

        return $sanitized !== '' ? substr($sanitized, 0, 250) : null;
    }

    /**
     * @param array<array-key, mixed> $attributes
     *
     * @return null|string
     */
    private function stringAttribute(array $attributes, string $key): ?string
    {
        /** @var mixed $value */
        $value = $attributes[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
