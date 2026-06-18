<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Validator\TwoFactorEmailValidator;

final class TwoFactorEmailValidatorTest extends TestCase
{
    public function testValidateSucceedsWithMatchingCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user, '654321');
        $result = $validator->validate();
        self::assertIsBool($result);
        self::assertTrue($result);
    }

    public function testValidateFailsWithNonMatchingCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertIsBool($result);
        self::assertFalse($result);
    }

    public function testValidateFailsWhenAuthTfKeyIsNull(): void
    {
        $user = new User();
        $user->setAuthTfKey(null);

        $validator = new TwoFactorEmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertIsBool($result);
        self::assertFalse($result);
    }

    public function testValidateFailsWhenAuthTfKeyIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new TwoFactorEmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertIsBool($result);
        self::assertFalse($result);
    }

    public function testValidateWithEmptyCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user);
        $result = $validator->validate();
        self::assertIsBool($result);
        self::assertFalse($result);
    }

    public function testValidateReturnType(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user, '654321');
        $result = $validator->validate();
        self::assertNotNull($result);
    }

    public function testGenerateCodeReturnsSixDigitString(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user);
        $code = $validator->generateCode();

        self::assertIsString($code);
        self::assertSame(6, strlen($code));
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testGenerateCodeReturnsRandomValues(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user);
        $code1 = $validator->generateCode();
        $code2 = $validator->generateCode();

        self::assertNotSame($code1, $code2);
    }

    public function testGetSuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user);
        self::assertSame(
            'Email two factor authentication has been enabled.',
            $validator->getSuccessMessage(),
        );
    }

    public function testGetUnsuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user);
        self::assertSame(
            'Invalid code. Please try again within 30 seconds.',
            $validator->getUnsuccessMessage(30),
        );
    }

    public function testGetUnsuccessLoginMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('654321');

        $validator = new TwoFactorEmailValidator($user);
        self::assertSame(
            'Invalid email verification code. Please try again within 60 seconds.',
            $validator->getUnsuccessLoginMessage(60),
        );
    }

    public function testConstructorWithDefaultCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('some-stored-code');

        $validator = new TwoFactorEmailValidator($user);
        self::assertFalse($validator->validate());
    }

    public function testValidateReturnTypeWithNullKey(): void
    {
        $user = new User();
        $user->setAuthTfKey(null);

        $validator = new TwoFactorEmailValidator($user, '123456');
        $result = $validator->validate();
        self::assertNotNull($result);
        self::assertFalse($result);
    }

    public function testValidateWithMatchingCodeAndExplicitBool(): void
    {
        $user = new User();
        $user->setAuthTfKey('999999');

        $validator = new TwoFactorEmailValidator($user, '999999');
        $result = $validator->validate();
        self::assertTrue($result === true);
    }

    public function testValidateWithEmptyCodeWhenStoredCodeIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new TwoFactorEmailValidator($user, '');
        self::assertFalse($validator->validate());
    }
}
