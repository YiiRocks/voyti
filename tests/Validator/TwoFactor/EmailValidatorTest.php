<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\TwoFactor;

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

    public function testValidateReturnsTrueWhenCodeMatches(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new EmailValidator($user, '123456');

        $this->assertTrue($validator->validate());
    }

    public static function unconfiguredKeyProvider(): iterable
    {
        yield 'empty key' => [''];
        yield 'null key' => [null];
    }
}
