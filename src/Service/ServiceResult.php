<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Enum\ServiceResultStatus;

/**
 * Immutable success/failure outcome returned by service `run()` methods, carrying an optional
 * message and validation errors.
 */
final readonly class ServiceResult
{
    public function __construct(
        private ServiceResultStatus $status,
        private string $message = '',
        private array $errors = [],
    ) {}

    public static function failure(string $message = '', array $errors = []): self
    {
        return new self(ServiceResultStatus::FAILURE, $message, $errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isFailure(): bool
    {
        return $this->status === ServiceResultStatus::FAILURE;
    }

    public function isSuccess(): bool
    {
        return $this->status === ServiceResultStatus::SUCCESS;
    }

    public static function success(string $message = ''): self
    {
        return new self(ServiceResultStatus::SUCCESS, $message);
    }
}
