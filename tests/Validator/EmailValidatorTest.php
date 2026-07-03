<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\TwoFactor {

    // Intercepts calls to the global random_int() made from within this namespace
    // (PHP resolves unqualified function calls against the current namespace first),
    // so tests can assert on the exact bounds EmailValidator::generateCode() passes.
    function random_int(int $min, int $max): int
    {
        \YiiRocks\Voyti\tests\Validator\EmailValidatorTest::$capturedMin = $min;
        \YiiRocks\Voyti\tests\Validator\EmailValidatorTest::$capturedMax = $max;

        return \random_int($min, $max);
    }
}

namespace YiiRocks\Voyti\tests\Validator {

    use PHPUnit\Framework\TestCase;
    use YiiRocks\Voyti\Entity\User;
    use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;

    final class EmailValidatorTest extends TestCase
    {
        public static ?int $capturedMax = null;
        public static ?int $capturedMin = null;

        protected function setUp(): void
        {
            parent::setUp();
            self::$capturedMax = null;
            self::$capturedMin = null;
        }

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

            self::assertMatchesRegularExpression('/^\d{6}$/', $code);
        }

        public function testGenerateCodeUsesExactRandomIntBounds(): void
        {
            $user = new User();
            $user->setAuthTfKey('654321');

            $validator = new EmailValidator($user);
            $validator->generateCode();

            self::assertSame(100000, self::$capturedMin);
            self::assertSame(999999, self::$capturedMax);
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

        public function testValidateWithEmptyCodeWhenStoredCodeIsEmpty(): void
        {
            // Both the stored key and the supplied code must be '' here: this is the only
            // case that catches a mutant removing the early `return false;` for an
            // unconfigured (empty stored key) validator, since the code falls through to
            // `$this->code === $storedCode`, which is only true when $this->code is also ''.
            $user = new User();
            $user->setAuthTfKey('');

            $validator = new EmailValidator($user, '');
            self::assertFalse($validator->validate());
        }
    }
}
