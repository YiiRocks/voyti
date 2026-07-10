<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\ServiceResult;

final readonly class RegisterService
{
    public function __construct(
        private UserCreationHelper $userCreationHelper,
        private ModuleConfig $config,
        private PasswordGeneratorInterface $passwordGenerator,
    ) {
    }

    public function run(array $data): ServiceResult
    {
        $username = isset($data['username']) && is_string($data['username']) ? $data['username'] : '';
        $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
        $password = isset($data['password']) && is_string($data['password']) && $data['password'] !== ''
            ? $data['password']
            : $this->passwordGenerator->generate(12);
        $gdprConsent = (bool) ($data['gdprConsent'] ?? false);

        $conflict = $this->userCreationHelper->findUniquenessConflict($email, $username);
        if ($conflict !== null) {
            return ServiceResult::failure($conflict, [$conflict]);
        }

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        $user->setRegistrationIp(
            $this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
        );
        $user->setGdprConsent($this->config->enableGdprCompliance ? $gdprConsent : false);
        if ($this->config->enableGdprCompliance && $gdprConsent) {
            $user->setGdprConsentDate(time());
        }

        $emailConfirmationRequired = $this->userCreationHelper->persistAndNotify($user, $password);

        return $emailConfirmationRequired
            ? ServiceResult::success('voyti.registration.account_created_check_email')
            : ServiceResult::success('voyti.registration.account_created');
    }
}
