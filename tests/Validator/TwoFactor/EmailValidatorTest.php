<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\TwoFactor;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;

final class EmailValidatorTest extends TestCase
{
    public function testGetErrorMessageDefault(): void
    {
        $validator = new EmailValidator($this->createTranslator());

        $this->assertSame('', $validator->getErrorMessage());
    }

    public function testValidateReturnsFalseWhenBothCodeAndKeyAreEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new EmailValidator($this->createTranslator());

        $this->assertFalse($validator->validate($user, ''));
        $this->assertSame('Email two factor authentication is not configured.', $validator->getErrorMessage());
    }

    public function testValidateReturnsTrueWhenCodeMatches(): void
    {
        $user = new User();
        $user->setAuthTfKey('123456');

        $validator = new EmailValidator($this->createTranslator());

        $this->assertTrue($validator->validate($user, '123456'));
    }
}
