<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use RuntimeException;
use YiiRocks\Voyti\Service\ServiceResult;

/**
 * Creates a user account with an admin-supplied password, delegating uniqueness checks and
 * persistence to {@see UserCreationHelper}. Used by admin user creation, not self-registration.
 */
final readonly class CreateService
{
    public function __construct(
        private UserCreationHelper $userCreationHelper,
    ) {}

    public function run(string $email, string $username, string $password): ServiceResult
    {
        $conflict = $this->userCreationHelper->findUniquenessConflict($email, $username);
        if ($conflict !== null) {
            /** @infection-ignore-all Equivalent: removing this early return falls through to persistAndNotify(), which re-guards uniqueness and rethrows a RuntimeException carrying the same conflict message, producing the identical failure ServiceResult with no user persisted. */
            return ServiceResult::failure($conflict);
        }

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        try {
            $this->userCreationHelper->persistAndNotify($user);
        } catch (RuntimeException $exception) {
            return ServiceResult::failure($exception->getMessage());
        }

        return ServiceResult::success('User has been created');
    }
}
