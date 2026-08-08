<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\TwoFactor;

use chillerlan\TwoFactorQRCode\TwoFactorQRCode;
use chillerlan\TwoFactorQRCode\TwoFactorQRCodeOptions;
use Throwable;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Verifies a TOTP two-factor authentication code against the user's stored secret using
 * {@see TwoFactorQRCode}, exposing a translated error message via {@see getErrorMessage()} on failure.
 */
final class CodeValidator
{
    private string $error = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function getErrorMessage(): string
    {
        return $this->error;
    }

    public function validate(User $user, string $code, int $cycles = 1): bool
    {
        if ($user->getAuthTfKey() === null || $user->getAuthTfKey() === '') {
            $this->error = $this->translator->translate('voyti.validator.two_factor_not_configured', category: 'voyti');
            return false;
        }

        if (!class_exists(TwoFactorQRCode::class)) {
            // @codeCoverageIgnoreStart
            $this->error = $this->translator->translate('voyti.validator.two_factor_library_missing', category: 'voyti');
            return false;
            // @codeCoverageIgnoreEnd
        }

        try {
            $options = new TwoFactorQRCodeOptions();
            $options->adjacent = $cycles;
            $totp = new TwoFactorQRCode($options);
            $totp->setSecret($user->getAuthTfKey());
            return $totp->verifyOTP($code);
        } catch (Throwable) {
            $this->error = $this->translator->translate('voyti.validator.invalid_verification_code', category: 'voyti');
            return false;
        }
    }
}
