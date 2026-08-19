<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use YiiRocks\Voyti\Event\Auth\BeforeRegisterEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\VoytiConfig;

/**
 * Handles self-registration from raw form data: enforces email/username uniqueness,
 * applies personal data processing consent and registration IP, dispatches the cancellable
 * {@see BeforeRegisterEvent} once the user is hydrated but not yet persisted (a listener may throw
 * {@see ActionPreventedException} to reject the registration), and delegates persistence/notification
 * to {@see UserCreationHelper}.
 */
final readonly class RegisterService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UserCreationHelper $userCreationHelper,
        private VoytiConfig $config,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $serverParams
     */
    public function run(array $data, array $serverParams = []): ServiceResult
    {
        $username = isset($data['username']) && is_string($data['username']) ? $data['username'] : '';
        $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
        $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';

        $conflict = $this->userCreationHelper->findUniquenessConflict($email, $username);
        if ($conflict !== null) {
            /** @infection-ignore-all Removing this early return is observationally equivalent: persist() re-guards uniqueness and rethrows a RuntimeException carrying the same conflict message, which the catch below turns into the identical failure ServiceResult with no user persisted. */
            return ServiceResult::failure($conflict, [$conflict]);
        }

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        $user->setRegistrationIp(LoginMetadataHelper::remoteAddr($serverParams));
        $user->setDataProcessingConsentDate(time());

        try {
            $this->eventDispatcher->dispatch(new BeforeRegisterEvent($data, $user));
            $emailConfirmationRequired = $this->userCreationHelper->persistAndNotify($user);
        } catch (ActionPreventedException $exception) {
            $errorDetails = $exception->getErrorDetails() !== [] ? $exception->getErrorDetails() : [$exception->getMessage()];
            return ServiceResult::failure($exception->getMessage(), $errorDetails);
        } catch (RuntimeException $exception) {
            return ServiceResult::failure($exception->getMessage(), [$exception->getMessage()]);
        }

        return $emailConfirmationRequired
            ? ServiceResult::success('voyti.registration.account_created_check_email')
            : ServiceResult::success('voyti.registration.account_created');
    }
}
