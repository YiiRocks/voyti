<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Validator\TwoFactor\TextMessageValidator;

final class TextMessageValidatorTest extends TestCase
{

    public function testConstructorWithDefaultCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('some-stored-code');

        $validator = new TextMessageValidator($user);
        self::assertFalse($validator->validate());
    }

    public function testGenerateCodeReturnsRandomValues(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user);
        $code1 = $validator->generateCode();
        $code2 = $validator->generateCode();

        self::assertNotSame($code1, $code2);
    }

    public function testGenerateCodeReturnsSixDigitString(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user);
        $code = $validator->generateCode();

        self::assertSame(6, strlen($code));
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testGetSuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user);
        self::assertSame(
            'SMS two factor authentication has been enabled.',
            $validator->getSuccessMessage(),
        );
    }

    public function testGetUnsuccessLoginMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user);
        self::assertSame(
            'Invalid SMS verification code. Please try again within 90 seconds.',
            $validator->getUnsuccessLoginMessage(90),
        );
    }

    public function testGetUnsuccessMessage(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user);
        self::assertSame(
            'Invalid code. Please try again within 45 seconds.',
            $validator->getUnsuccessMessage(45),
        );
    }

    public function testValidateFailsWhenAuthTfKeyIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new TextMessageValidator($user, '123456');
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateFailsWhenAuthTfKeyIsNull(): void
    {
        $user = new User();
        $user->setAuthTfKey(null);

        $validator = new TextMessageValidator($user, '123456');
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateFailsWithNonMatchingCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user, '654321');
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateReturnType(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user, '123456');
        $result = $validator->validate();
        self::assertTrue($result);
    }
    public function testValidateSucceedsWithMatchingCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user, '123456');
        $result = $validator->validate();
        self::assertTrue($result);
    }

    public function testValidateWithEmptyCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new TextMessageValidator($user);
        $result = $validator->validate();
        self::assertFalse($result);
    }

    public function testValidateWithEmptyCodeWhenStoredCodeIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new TextMessageValidator($user, '');
        self::assertFalse($validator->validate());
    }

    public function testValidateWithMatchingCodeAndCustomKey(): void
    {
        $user = new User();
        $user->setAuthTfKey('888888');

        $validator = new TextMessageValidator($user, '888888');
        self::assertTrue($validator->validate());
    }
}
