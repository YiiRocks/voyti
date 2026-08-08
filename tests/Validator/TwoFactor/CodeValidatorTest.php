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

        $validator = new CodeValidator($this->createTranslator());
        $validator->validate($user, '123456');
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

        $validator = new CodeValidator($this->createTranslator());

        $this->assertTrue($validator->validate($user, $code));
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

        $validator = new CodeValidator($this->createTranslator());

        $this->assertFalse($validator->validate($user, $code));
    }

    #[DataProvider('unconfiguredTwoFactorKeyProvider')]
    public function testValidateReturnsFalseWhenKeyIsNotConfigured(?string $authTfKey): void
    {
        $user = new User();
        if ($authTfKey !== null) {
            $user->setAuthTfKey($authTfKey);
        }

        $validator = new CodeValidator($this->createTranslator());
        $this->assertFalse($validator->validate($user, '123456'));
    }

    public function testValidateWithValidAuthTfKeyAndInvalidCode(): void
    {
        $user = new User();
        $user->setAuthTfKey('VEVTVFNlY3JldEtleTEyMw==');

        $validator = new CodeValidator($this->createTranslator());
        $this->assertFalse($validator->validate($user, '000000'));
    }

    public static function unconfiguredTwoFactorKeyProvider(): iterable
    {
        yield 'empty key' => [''];
        yield 'null key' => [null];
    }
}
