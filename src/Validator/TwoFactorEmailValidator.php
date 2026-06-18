<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use YiiRocks\Voyti\Entity\User;

final class TwoFactorEmailValidator
{
    private string $error = '';
    private string $generatedCode = '';

    public function __construct(
        private readonly User $user,
        private readonly string $code = '',
    ) {
    }

    public function validate(): bool
    {
        $storedCode = $this->user->getAuthTfKey() ?? '';
        if ($storedCode === '') {
            $this->error = 'Email 2FA is not configured.';
            return false;
        }
        return $this->code === $storedCode;
    }

    public function generateCode(): string
    {
        $this->generatedCode = (string)random_int(100000, 999999);
        return $this->generatedCode;
    }

    public function getSuccessMessage(): string
    {
        return 'Email two factor authentication has been enabled.';
    }

    public function getUnsuccessMessage(int $timeDuration): string
    {
        return "Invalid code. Please try again within {$timeDuration} seconds.";
    }

    public function getUnsuccessLoginMessage(int $timeDuration): string
    {
        return "Invalid email verification code. Please try again within {$timeDuration} seconds.";
    }
}
