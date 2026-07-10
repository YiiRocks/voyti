<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Service\ServiceResult;

final readonly class CreateService
{
    public function __construct(
        private UserCreationHelper $userCreationHelper,
    ) {
    }

    public function run(string $email, string $username, string $password): ServiceResult
    {
        $conflict = $this->userCreationHelper->findUniquenessConflict($email, $username);
        if ($conflict !== null) {
            return ServiceResult::failure($conflict);
        }

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        $this->userCreationHelper->persistAndNotify($user, $password);

        return ServiceResult::success('User has been created');
    }
}
