<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

/**
 * Dispatched in `SessionController::login()` once the login form is populated from the request but
 * before it is validated, carrying the raw submitted `formData` and the request's server params.
 */
final readonly class BeforeLoginFormValidationEvent
{
    /**
     * @param array<array-key, mixed> $formData
     * @param array<array-key, mixed> $serverParams
     */
    public function __construct(
        private array $formData,
        private array $serverParams = [],
    ) {}

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
