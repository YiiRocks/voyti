<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use chillerlan\Authenticator\Authenticator;
use chillerlan\Authenticator\AuthenticatorOptions;
use chillerlan\Authenticator\Common\Base32;
use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Validator\TwoFactor\CodeValidator;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;

/** @psalm-suppress PropertyNotSetInConstructor */
final class CodeValidatorTest extends TestCase
{
    private TranslatorInterface $translator;

    #[\Override]
    final protected function setUp(): void
    {
        $this->translator = new class implements TranslatorInterface {
            #[\Override]
            public function addCategorySources(CategorySource ...$categories): static
            {
                return $this;
            }
            #[\Override]
            public function setLocale(string $locale): static
            {
                return $this;
            }
            #[\Override]
            public function getLocale(): string
            {
                return 'en';
            }
            #[\Override]
            public function translate(
                string|Stringable $id,
                array $parameters = [],
                ?string $category = null,
                ?string $locale = null,
            ): string {
                return match ((string)$id) {
                    'voyti.validator.two_factor_enabled' => 'Two factor authentication has been enabled.',
                    'voyti.validator.invalid_code_with_time' => 'Invalid code. Please try again within ' . ((string) ($parameters['timeDuration'] ?? '?')) . ' seconds.',
                    'voyti.validator.invalid_two_factor_code_with_time' => 'Invalid two factor authentication code. Please try again within ' . ((string) ($parameters['timeDuration'] ?? '?')) . ' seconds.',
                    default => (string)$id,
                };
            }
            #[\Override]
            public function withDefaultCategory(string $category): static
            {
                return $this;
            }
            #[\Override]
            public function withLocale(string $locale): static
            {
                return $this;
            }
        };
    }

    public function testDefaultSuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey(Base32::encode(random_bytes(10)));

        $validator = new CodeValidator($user, '123456');
        self::assertSame('voyti.validator.two_factor_enabled', $validator->getSuccessMessage());
    }

    public function testGetErrorMessageReturnsEmptyBeforeValidation(): void
    {
        $user = new User();
        $user->setAuthTfKey(Base32::encode(random_bytes(10)));

        $validator = new CodeValidator($user, '123456');
        self::assertSame('', $validator->getErrorMessage());
    }

    public function testGetUnsuccessLoginMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey(Base32::encode(random_bytes(10)));

        $validator = new CodeValidator($user, '123456');
        self::assertSame('voyti.validator.invalid_two_factor_code_with_time', $validator->getUnsuccessLoginMessage(30));
    }

    public function testGetUnsuccessLoginMessageWithTranslator(): void
    {
        $user = new User();
        $user->setAuthTfKey(Base32::encode(random_bytes(10)));

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($this->translator);
        self::assertSame(
            'Invalid two factor authentication code. Please try again within 30 seconds.',
            $validator->getUnsuccessLoginMessage(30),
        );
    }

    public function testGetUnsuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey(Base32::encode(random_bytes(10)));

        $validator = new CodeValidator($user, '123456');
        self::assertSame('voyti.validator.invalid_code_with_time', $validator->getUnsuccessMessage(30));
    }

    public function testGetUnsuccessMessageWithTranslator(): void
    {
        $user = new User();
        $user->setAuthTfKey(Base32::encode(random_bytes(10)));

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($this->translator);
        self::assertSame(
            'Invalid code. Please try again within 30 seconds.',
            $validator->getUnsuccessMessage(30),
        );
    }

    public function testSuccessMessageWithTranslator(): void
    {
        $user = new User();
        $user->setAuthTfKey(Base32::encode(random_bytes(10)));

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($this->translator);
        self::assertSame('Two factor authentication has been enabled.', $validator->getSuccessMessage());
    }

    public function testValidateAcceptsAdjacentCodeWithDefaultCycles(): void
    {
        $secret = Base32::encode(random_bytes(10));

        $options = new AuthenticatorOptions();
        $authenticator = new Authenticator($options);
        $authenticator->setSecret($secret);
        $adjacentCode = $authenticator->code(time() - $options->period);

        $user = new User();
        $user->setAuthTfKey($secret);

        $validator = new CodeValidator($user, $adjacentCode);
        $this->assertTrue($validator->validate());
    }

    public function testValidateCatchesExceptionFromAuthenticator(): void
    {
        $user = new User();
        $user->setAuthTfKey('invalid!base32!!');

        $validator = new CodeValidator($user, '');
        self::assertFalse($validator->validate());
        self::assertSame('voyti.validator.invalid_verification_code', $validator->getErrorMessage());
    }

    public function testValidateFailsWhenAuthTfKeyIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new CodeValidator($user, '123456');
        self::assertFalse($validator->validate());
        self::assertSame('voyti.validator.two_factor_not_configured', $validator->getErrorMessage());
    }

    public function testValidateFailsWhenAuthTfKeyIsNull(): void
    {
        $user = new User();
        $user->setAuthTfKey(null);

        $validator = new CodeValidator($user, '123456');
        self::assertFalse($validator->validate());
        self::assertSame('voyti.validator.two_factor_not_configured', $validator->getErrorMessage());
    }

    public function testValidateFailsWithWrongCode(): void
    {
        $secret = Base32::encode(random_bytes(10));

        $user = new User();
        $user->setAuthTfKey($secret);

        $validator = new CodeValidator($user, '000000');
        self::assertFalse($validator->validate());
    }

    public function testValidateRejectsCodeFromTwoWindowsAwayWithDefaultCycles(): void
    {
        $secret = Base32::encode(random_bytes(10));

        $options = new AuthenticatorOptions();
        $authenticator = new Authenticator($options);
        $authenticator->setSecret($secret);
        $codeFarAway = $authenticator->code(time() - $options->period * 2);

        $user = new User();
        $user->setAuthTfKey($secret);

        $validator = new CodeValidator($user, $codeFarAway);
        $this->assertFalse($validator->validate());
    }

    public function testValidateWithAdjacentCycles(): void
    {
        $secret = Base32::encode(random_bytes(10));

        $options = new AuthenticatorOptions();
        $options->adjacent = 2;
        $authenticator = new Authenticator($options);
        $authenticator->setSecret($secret);
        $currentCode = $authenticator->code();

        $user = new User();
        $user->setAuthTfKey($secret);

        $validator = new CodeValidator($user, $currentCode, 2);
        self::assertTrue($validator->validate());
    }

    public function testValidateWithDefaultCycles(): void
    {
        $secret = Base32::encode(random_bytes(10));

        $options = new AuthenticatorOptions();
        $authenticator = new Authenticator($options);
        $authenticator->setSecret($secret);
        $currentCode = $authenticator->code();

        $user = new User();
        $user->setAuthTfKey($secret);

        $validator = new CodeValidator($user, $currentCode);
        $this->assertTrue($validator->validate());
    }
}
