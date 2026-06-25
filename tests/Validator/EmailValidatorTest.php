<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;

final class EmailValidatorTest extends TestCase
{

    public function testConstructorWithDefaultCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('some-stored-code');

        $validator = new EmailValidator($user);
        self::assertFalse($validator->validate());
    }

    public function testGenerateCodeReturnsRandomValues(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user);
        $code1 = $validator->generateCode();
        $code2 = $validator->generateCode();

        self::assertNotSame($code1, $code2);
    }

    public function testGenerateCodeReturnsSixDigitString(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user);
        $code = $validator->generateCode();

        self::assertSame(6, strlen($code));
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testGetSuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user);
        self::assertSame(
            'Email two factor authentication has been enabled.',
            $validator->getSuccessMessage(),
        );
    }

    public function testGetUnsuccessLoginMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user);
        self::assertSame(
            'Invalid email verification code. Please try again within 60 seconds.',
            $validator->getUnsuccessLoginMessage(60),
        );
    }

    public function testGetUnsuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user);
        self::assertSame(
            'Invalid code. Please try again within 30 seconds.',
            $validator->getUnsuccessMessage(30),
        );
    }

    public function testValidateFailsWhenAuthTfKeyIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new EmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateFailsWhenAuthTfKeyIsNull(): void
    {
        $user = new User();
        $user->setAuthTfKey(null);

        $validator = new EmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateFailsWithNonMatchingCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateReturnType(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user, '654321');
        $result = $validator->validate();
        self::assertTrue($result);
    }

    public function testValidateReturnTypeWithNullKey(): void
    {
        $user = new User();
        $user->setAuthTfKey(null);

        $validator = new EmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertFalse($result);
    }
    public function testValidateSucceedsWithMatchingCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user, '654321');
        $result = $validator->validate();
        self::assertTrue($result);
    }

    public function testValidateWithEmptyCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new EmailValidator($user);
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateWithEmptyCodeWhenStoredCodeIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new EmailValidator($user, '');
        self::assertFalse($validator->validate());
    }

    public function testValidateWithMatchingCodeAndExplicitBool(): void
    {
        $user = new User();
        $user->setAuthTfKey('999999');

        $validator = new EmailValidator($user, '999999');
        $result = $validator->validate();
        self::assertTrue($result === true);
    }
}
