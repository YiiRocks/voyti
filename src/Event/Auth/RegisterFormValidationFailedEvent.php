<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

/**
 * Dispatched in `RegistrationController::register()` when the registration form fails validation,
 * carrying the submitted `formData`, the validation `errors`, and the request's server params.
 * Informational only - validation has already failed by the time this fires.
 */
final readonly class RegisterFormValidationFailedEvent
{
    /**
     * @param array<array-key, mixed> $formData
     * @param array<array-key, mixed> $errors
     * @param array<array-key, mixed> $serverParams
     */
    public function __construct(
        private array $formData,
        private array $errors,
        private array $serverParams = [],
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }
}
