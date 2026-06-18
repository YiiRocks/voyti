<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use Yiisoft\Translator\TranslatorInterface;
use YiiRocks\Voyti\Entity\User;

final class TwoFactorCodeValidator
{
    private string $error = '';
    private ?TranslatorInterface $translator = null;

    public function __construct(
        private readonly User $user,
        private readonly string $code,
        private readonly int $cycles = 1,
    ) {
    }

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    private function t(string $id, array $parameters = []): string
    {
        return $this->translator?->translate($id, $parameters) ?? $id;
    }

    public function validate(): bool
    {
        if ($this->user->getAuthTfKey() === null || $this->user->getAuthTfKey() === '') {
            $this->error = $this->t('voyti.validator.two_factor_not_configured');
            return false;
        }

        if (!class_exists('\chillerlan\Authenticator\Authenticator')) {
            $this->error = $this->t('voyti.validator.two_factor_library_missing');
            return false;
        }

        try {
            $options = new \chillerlan\Authenticator\AuthenticatorOptions();
            $options->adjacent = $this->cycles;
            $authenticator = new \chillerlan\Authenticator\Authenticator($options);
            $authenticator->setSecret($this->user->getAuthTfKey());
            return $authenticator->verify($this->code);
        } catch (\Throwable) {
            $this->error = $this->t('voyti.validator.invalid_verification_code');
            return false;
        }
    }

    public function getErrorMessage(): string
    {
        return $this->error;
    }

    public function getSuccessMessage(): string
    {
        return $this->t('voyti.validator.two_factor_enabled');
    }

    public function getUnsuccessMessage(int $timeDuration): string
    {
        return $this->t('voyti.validator.invalid_code_with_time', ['timeDuration' => $timeDuration]);
    }

    public function getUnsuccessLoginMessage(int $timeDuration): string
    {
        return $this->t('voyti.validator.invalid_two_factor_code_with_time', ['timeDuration' => $timeDuration]);
    }
}
