<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\TwoFactor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;

final class EmailValidatorTest extends TestCase
{
    public function testGetErrorMessageDefault(): void
    {
        $user = new User();
        $validator = new EmailValidator($user, '');

        $this->assertSame('', $validator->getErrorMessage());
    }

    public function testGetSuccessMessage(): void
    {
        $user = new User();
        $validator = new EmailValidator($user, '');

        $this->assertSame('Email two factor authentication has been enabled.', $validator->getSuccessMessage());
    }

    public function testGetUnsuccessLoginMessage(): void
    {
        $user = new User();
        $validator = new EmailValidator($user, '');

        $this->assertStringContainsString('30', $validator->getUnsuccessLoginMessage(30));
    }

    public function testGetUnsuccessMessage(): void
    {
        $user = new User();
        $validator = new EmailValidator($user, '');

        $this->assertStringContainsString('30', $validator->getUnsuccessMessage(30));
    }

    public function testValidateReturnsFalseWhenBothCodeAndKeyAreEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new EmailValidator($user, '');

        $this->assertFalse($validator->validate());
        $this->assertSame('Email 2FA is not configured.', $validator->getErrorMessage());
    }

    public function testValidateReturnsFalseWhenCodeDoesNotMatch(): void
    {
        $user = new User();
        $user->setAuthTfKey('stored_code');

        $validator = new EmailValidator($user, 'wrong_code');

        $this->assertFalse($validator->validate());
    }

    #[DataProvider('unconfiguredKeyProvider')]
    public function testValidateReturnsFalseWhenKeyIsNotConfigured(?string $authTfKey): void
    {
        $user = new User();
        if ($authTfKey !== null) {
            $user->setAuthTfKey($authTfKey);
        }

        $validator = new EmailValidator($user, '123456');

        $this->assertFalse($validator->validate());
        $this->assertSame('Email 2FA is not configured.', $validator->getErrorMessage());
    }

    public function testValidateReturnsTrueWhenCodeMatches(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new EmailValidator($user, '123456');

        $this->assertTrue($validator->validate());
    }

    /**
     * @return iterable<string, array{null|string}>
     */
    public static function unconfiguredKeyProvider(): iterable
    {
        yield 'empty key' => [''];
        yield 'null key' => [null];
    }
}
