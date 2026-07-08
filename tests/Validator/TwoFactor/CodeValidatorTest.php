<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Validator\TwoFactor\CodeValidator;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CodeValidatorTest extends TestCase
{

    public function testGetErrorMessageWhenKeyIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new CodeValidator($user, '123456');
        $validator->validate();
        $this->assertSame('voyti.validator.two_factor_not_configured', $validator->getErrorMessage());
    }

    public function testGetErrorMessageWhenKeyIsNull(): void
    {
        $user = new User();

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
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturn('2FA enabled');

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($translator);

        $this->assertSame('2FA enabled', $validator->getSuccessMessage());
    }

    public function testGetUnsuccessLoginMessagePassesTimeDurationParameter(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects(self::once())
            ->method('translate')
            ->with(
                'voyti.validator.invalid_two_factor_code_with_time',
                self::callback(static fn (array $p): bool => ($p['timeDuration'] ?? null) === 30),
                'voyti',
            )
            ->willReturn('translated');

        $validator = new CodeValidator(new User(), '123456');
        $validator->setTranslator($translator);

        $this->assertSame('translated', $validator->getUnsuccessLoginMessage(30));
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
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturn('Invalid 2FA code');

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($translator);

        $this->assertSame('Invalid 2FA code', $validator->getUnsuccessLoginMessage(30));
    }

    public function testGetUnsuccessMessagePassesTimeDurationParameter(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects(self::once())
            ->method('translate')
            ->with(
                'voyti.validator.invalid_code_with_time',
                self::callback(static fn (array $p): bool => ($p['timeDuration'] ?? null) === 30),
                'voyti',
            )
            ->willReturn('translated');

        $validator = new CodeValidator(new User(), '123456');
        $validator->setTranslator($translator);

        $this->assertSame('translated', $validator->getUnsuccessMessage(30));
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
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturn('Invalid code');

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($translator);

        $this->assertSame('Invalid code', $validator->getUnsuccessMessage(30));
    }

    public function testTranslateErrorMessageWhenKeyIsNull(): void
    {
        $user = new User();

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturn('translated message');

        $validator = new CodeValidator($user, '123456');
        $validator->setTranslator($translator);
        $validator->validate();
        $this->assertSame('translated message', $validator->getErrorMessage());
    }

    public function testValidateAcceptsPreviousWindowCodeWithDefaultCycles(): void
    {
        if (!class_exists(\chillerlan\Authenticator\Authenticator::class)) {
            $this->markTestSkipped('chillerlan/php-authenticator not installed.');
        }

        $now = time();
        $secret = (new \chillerlan\Authenticator\Authenticator())->createSecret();
        $user = new User();
        $user->setAuthTfKey($secret);

        $authenticator = new \chillerlan\Authenticator\Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code($now - 30);

        $validator = new CodeValidator($user, $code);

        $this->assertTrue($validator->validate());
    }

    public function testValidateRejectsTwoWindowsBackCodeWithDefaultCycles(): void
    {
        if (!class_exists(\chillerlan\Authenticator\Authenticator::class)) {
            $this->markTestSkipped('chillerlan/php-authenticator not installed.');
        }

        $now = time();
        $secret = (new \chillerlan\Authenticator\Authenticator())->createSecret();
        $user = new User();
        $user->setAuthTfKey($secret);

        $authenticator = new \chillerlan\Authenticator\Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code($now - 60);

        $validator = new CodeValidator($user, $code);

        $this->assertFalse($validator->validate());
    }

    public function testValidateReturnsFalseWhenKeyIsEmpty(): void
    {
        $user = new User();
        $user->setAuthTfKey('');

        $validator = new CodeValidator($user, '123456');
        $this->assertFalse($validator->validate());
    }
    public function testValidateReturnsFalseWhenKeyIsNull(): void
    {
        $user = new User();

        $validator = new CodeValidator($user, '123456');
        $this->assertFalse($validator->validate());
    }

    public function testValidateReturnsTrueWithValidCurrentCode(): void
    {
        if (!class_exists(\chillerlan\Authenticator\Authenticator::class)) {
            $this->markTestSkipped('chillerlan/php-authenticator not installed.');
        }

        $secret = (new \chillerlan\Authenticator\Authenticator())->createSecret();
        $user = new User();
        $user->setAuthTfKey($secret);

        $authenticator = new \chillerlan\Authenticator\Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $validator = new CodeValidator($user, $code);

        $this->assertTrue($validator->validate());
    }

    public function testValidateWithValidAuthTfKeyAndInvalidCode(): void
    {
        if (!class_exists(\chillerlan\Authenticator\Authenticator::class)) {
            $this->markTestSkipped('chillerlan/php-authenticator not installed.');
        }

        $user = new User();
        $user->setAuthTfKey('VEVTVFNlY3JldEtleTEyMw==');

        $validator = new CodeValidator($user, '000000');
        $this->assertFalse($validator->validate());
    }
}
