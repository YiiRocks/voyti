<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\TwoFactor;

use YiiRocks\Voyti\Model\User;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Generates and verifies email-delivered two-factor authentication codes by comparing against the
 * user's stored code.
 */
final class EmailValidator
{
    private string $error = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function getErrorMessage(): string
    {
        return $this->error;
    }

    public function validate(User $user, string $code): bool
    {
        $storedCode = $user->getAuthTfKey() ?? '';
        if ($storedCode === '') {
            $this->error = $this->translator->translate('voyti.validator.email_two_factor_not_configured', category: 'voyti');
            return false;
        }
        return hash_equals($storedCode, $code);
    }
}
