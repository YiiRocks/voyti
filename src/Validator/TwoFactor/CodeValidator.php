<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use chillerlan\Authenticator\AuthenticatorOptions;
use Throwable;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Verifies a TOTP two-factor authentication code against the user's stored secret using
 * {@see Authenticator}, producing translated success/error messages for the login and settings flows.
 */
final class CodeValidator
{
    private string $error = '';
    private ?TranslatorInterface $translator = null;

    public function __construct(
        private readonly User $user,
        private readonly string $code,
        private readonly int $cycles = 1,
    ) {}

    public function getErrorMessage(): string
    {
        return $this->error;
    }

    public function getSuccessMessage(): string
    {
        return $this->t('voyti.validator.two_factor_enabled');
    }

    public function getUnsuccessLoginMessage(int $timeDuration): string
    {
        return $this->t('voyti.validator.invalid_two_factor_code_with_time', ['timeDuration' => $timeDuration]);
    }

    public function getUnsuccessMessage(int $timeDuration): string
    {
        return $this->t('voyti.validator.invalid_code_with_time', ['timeDuration' => $timeDuration]);
    }

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function validate(): bool
    {
        if ($this->user->getAuthTfKey() === null || $this->user->getAuthTfKey() === '') {
            $this->error = $this->t('voyti.validator.two_factor_not_configured');
            return false;
        }

        if (!class_exists(Authenticator::class)) {
            // @codeCoverageIgnoreStart
            // Only reachable when chillerlan/php-authenticator is missing; always installed in the test environment.
            $this->error = $this->t('voyti.validator.two_factor_library_missing');
            return false;
            // @codeCoverageIgnoreEnd
        }

        try {
            $options = new AuthenticatorOptions();
            $options->adjacent = $this->cycles;
            $authenticator = new Authenticator($options);
            $authenticator->setSecret($this->user->getAuthTfKey());
            return $authenticator->verify($this->code);
        } catch (Throwable) {
            $this->error = $this->t('voyti.validator.invalid_verification_code');
            return false;
        }
    }

    private function t(string $id, array $parameters = []): string
    {
        return $this->translator?->translate($id, $parameters, category: 'voyti') ?? $id;
    }
}
