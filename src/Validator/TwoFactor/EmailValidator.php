<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\TwoFactor;

use YiiRocks\Voyti\Model\User;

final class EmailValidator
{
    private string $error = '';
    private string $generatedCode = '';

    public function __construct(
        private readonly User $user,
        private readonly string $code = '',
    ) {
    }

    /**
     * @return numeric-string
     */
    public function generateCode(): string
    {
        /**
         * @infection-ignore-all
         *
         * random_int(100000, 999999) is non-deterministic — no test can
         * assert the exact value.  Boundary mutants (100001, 999998) are
         * indistinguishable from the real output.
         */
        $this->generatedCode = (string)random_int(100000, 999999);
        return $this->generatedCode;
    }

    public function getErrorMessage(): string
    {
        return $this->error;
    }

    public function getSuccessMessage(): string
    {
        return 'Email two factor authentication has been enabled.';
    }

    public function getUnsuccessLoginMessage(int $timeDuration): string
    {
        return "Invalid email verification code. Please try again within {$timeDuration} seconds.";
    }

    public function getUnsuccessMessage(int $timeDuration): string
    {
        return "Invalid code. Please try again within {$timeDuration} seconds.";
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
}
