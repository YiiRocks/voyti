<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Exception;

use RuntimeException;

final class PasswordPolicyViolationException extends RuntimeException
{
    /**
     * @param non-empty-list<string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct($errors[0]);
    }

    /**
     * @return non-empty-list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
