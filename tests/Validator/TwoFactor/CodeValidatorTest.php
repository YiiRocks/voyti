<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\Validator\TwoFactor\CodeValidator;

#[AllowMockObjectsWithoutExpectations]
final class CodeValidatorTest extends TestCase
{
    #[DataProvider('unconfiguredTwoFactorKeyProvider')]
    public function testGetErrorMessageWhenKeyIsNotConfigured(?string $authTfKey): void
    {
        $user = new User();
        if ($authTfKey !== null) {
            $user->setAuthTfKey($authTfKey);
        }

        $validator = new CodeValidator($user, '123456');
        $validator->validate();
        $this->assertSame('voyti.validator.two_factor_not_configured', $validator->getErrorMessage());
    }

    public function testGetSuccessMessageWithoutTranslator(): void
    {
        $user = new User();
        $validator = new CodeValidator($user, '123456');

        $this->assertSame('voyti.validator.two_factor_enabled', $validator->getSuccessMessage());
    }

    public function testGetSuccessMessageWithTranslator(): void
    {
        $user = new User();
        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($this->createTranslator());

        $this->assertSame('Two factor authentication has been enabled.', $validator->getSuccessMessage());
    }

    public function testGetUnsuccessLoginMessageWithoutTranslator(): void
    {
        $user = new User();
        $validator = new CodeValidator($user, '123456');

        $this->assertSame(
            'voyti.validator.invalid_two_factor_code_with_time',
            $validator->getUnsuccessLoginMessage(30),
        );
    }

    public function testGetUnsuccessLoginMessageWithTranslator(): void
    {
        $user = new User();
        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($this->createTranslator());

        $this->assertSame(
            'Invalid two factor authentication code. Please try again within 30 seconds.',
            $validator->getUnsuccessLoginMessage(30),
        );
    }

    public function testGetUnsuccessMessageWithoutTranslator(): void
    {
        $user = new User();
        $validator = new CodeValidator($user, '123456');

        $this->assertSame(
            'voyti.validator.invalid_code_with_time',
            $validator->getUnsuccessMessage(30),
        );
    }

    public function testGetUnsuccessMessageWithTranslator(): void
    {
        $user = new User();
        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($this->createTranslator());

        $this->assertSame(
            'Invalid code. Please try again within 30 seconds.',
            $validator->getUnsuccessMessage(30),
        );
    }

    public function testTranslateErrorMessageWhenKeyIsNull(): void
    {
        $user = new User();

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($this->createTranslator());
        $validator->validate();
        $this->assertSame('Two factor authentication is not configured.', $validator->getErrorMessage());
    }

    public function testValidateAcceptsPreviousWindowCodeWithDefaultCycles(): void
    {
        $now = time();
        $secret = (new Authenticator())->createSecret();
        $user = new User();
        $user->setAuthTfKey($secret);

        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code($now - 30);

        $validator = new CodeValidator($user, $code);

        $this->assertTrue($validator->validate());
    }

    public function testValidateRejectsTwoWindowsBackCodeWithDefaultCycles(): void
    {
        $now = time();
        $secret = (new Authenticator())->createSecret();
        $user = new User();
        $user->setAuthTfKey($secret);

        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code($now - 60);

        $validator = new CodeValidator($user, $code);

        $this->assertFalse($validator->validate());
    }

    #[DataProvider('unconfiguredTwoFactorKeyProvider')]
    public function testValidateReturnsFalseWhenKeyIsNotConfigured(?string $authTfKey): void
    {
        $user = new User();
        if ($authTfKey !== null) {
            $user->setAuthTfKey($authTfKey);
        }

        $validator = new CodeValidator($user, '123456');
        $this->assertFalse($validator->validate());
    }

    public function testValidateReturnsTrueWithValidCurrentCode(): void
    {
        $secret = (new Authenticator())->createSecret();
        $user = new User();
        $user->setAuthTfKey($secret);

        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $validator = new CodeValidator($user, $code);

        $this->assertTrue($validator->validate());
    }

    public function testValidateWithValidAuthTfKeyAndInvalidCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('VEVTVFNlY3JldEtleTEyMw==');

        $validator = new CodeValidator($user, '000000');
        $this->assertFalse($validator->validate());
    }

    /**
     * @return iterable<string, array{null|string}>
     */
    public static function unconfiguredTwoFactorKeyProvider(): iterable
    {
        yield 'empty key' => [''];
        yield 'null key' => [null];
    }
}
