<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use YiiRocks\Voyti\Exception\PasswordPolicyViolationException;
use YiiRocks\Voyti\PasswordPolicyConfig;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;

use function mb_strlen;
use function preg_match_all;

final readonly class PasswordPolicy
{
    public function __construct(
        private PasswordPolicyConfig $config,
        private TranslatorInterface $translator,
    ) {}

    public function assertValid(string $password): void
    {
        $errors = $this->validate($password)->getErrorMessages();
        if ($errors !== []) {
            throw new PasswordPolicyViolationException($errors);
        }
    }

    public function rule(): Callback
    {
        return new Callback(
            callback: fn(string $value): Result => $this->validate($value),
        );
    }

    public function validate(string $password): Result
    {
        $result = new Result();
        $length = mb_strlen($password);

        if ($length < $this->config->minLength) {
            $this->addError($result, 'voyti.validator.password_min_length', $this->config->minLength);
        }
        if ($length > $this->config->maxLength) {
            $this->addError($result, 'voyti.validator.password_max_length', $this->config->maxLength);
        }

        $this->validateCharacterCount($result, $password, '/\p{Lu}/u', $this->config->minUppercase, 'uppercase');
        $this->validateCharacterCount($result, $password, '/\p{Ll}/u', $this->config->minLowercase, 'lowercase');
        $this->validateCharacterCount($result, $password, '/\p{Nd}/u', $this->config->minDigits, 'digits');
        $this->validateCharacterCount($result, $password, '/[^\p{L}\p{N}]/u', $this->config->minSymbols, 'symbols');

        return $result;
    }

    private function addError(Result $result, string $message, int $minimum): void
    {
        $result->addErrorWithoutPostProcessing(
            $this->translator->translate($message, ['minimum' => $minimum], category: 'voyti'),
        );
    }

    /**
     * @param non-empty-string $pattern
     */
    private function validateCharacterCount(
        Result $result,
        string $password,
        string $pattern,
        int $minimum,
        string $type,
    ): void {
        if (preg_match_all($pattern, $password) < $minimum) {
            $this->addError($result, 'voyti.validator.password_min_' . $type, $minimum);
        }
    }
}
