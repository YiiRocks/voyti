<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Service\ServiceResult;
use Yiisoft\Session\SessionInterface;

final class PendingSocialAccountService
{
    private const SESSION_KEY = 'social_network_account_code';

    public function __construct(
        private readonly UserSocialAccountRepository $userSocialAccountRepository,
        private readonly SessionInterface $session,
    ) {
    }

    public function connect(User $user): ServiceResult
    {
        $account = $this->getPendingAccount();
        if ($account === null) {
            return ServiceResult::success();
        }

        $userId = $user->getId();
        if ($account->isConnected() && $account->getUserId() !== (int) $userId) {
            return ServiceResult::failure('This account has already been connected to another user');
        }

        $account->connect($user);
        $this->clear();

        return ServiceResult::success();
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    public function getPendingAccount(): ?UserSocialAccount
    {
        $code = $this->code();
        if ($code === null) {
            return null;
        }

        $account = $this->userSocialAccountRepository->findByCode($code);
        if ($account === null || $account->isConnected()) {
            $this->clear();
            return null;
        }

        return $account;
    }

    public function remember(UserSocialAccount $account): void
    {
        $code = $account->getCode();
        if ($code !== null && $code !== '') {
            $this->session->set(self::SESSION_KEY, $code);
        }
    }

    public function useCode(string $code): ?UserSocialAccount
    {
        $account = $this->userSocialAccountRepository->findByCode($code);
        if ($account === null || $account->isConnected()) {
            $this->clear();
            return null;
        }

        $this->session->set(self::SESSION_KEY, $code);

        return $account;
    }

    private function code(): string|null
    {
        $code = $this->session->get(self::SESSION_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }
}
