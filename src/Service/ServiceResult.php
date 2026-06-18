<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

final class ServiceResult
{
    public function __construct(
        private readonly ServiceResultStatus $status,
        private readonly string $message = '',
        private readonly array $errors = [],
    ) {
    }

    public static function success(string $message = ''): self
    {
        return new self(ServiceResultStatus::SUCCESS, $message);
    }

    public static function failure(string $message = '', array $errors = []): self
    {
        return new self(ServiceResultStatus::FAILURE, $message, $errors);
    }

    public function isSuccess(): bool
    {
        return $this->status === ServiceResultStatus::SUCCESS;
    }

    public function isFailure(): bool
    {
        return $this->status === ServiceResultStatus::FAILURE;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
