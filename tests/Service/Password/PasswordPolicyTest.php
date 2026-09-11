<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Exception\PasswordPolicyViolationException;
use YiiRocks\Voyti\PasswordPolicyConfig;
use YiiRocks\Voyti\Service\Password\PasswordPolicy;
use YiiRocks\Voyti\tests\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public static function invalidPasswordProvider(): iterable
    {
        yield 'too short' => ['Ab1!', 'at least 8 characters'];
        yield 'too long' => ['Abcdefgh1!', 'at most 9 characters'];
        yield 'uppercase' => ['abcde12!!', 'at least 2 uppercase'];
        yield 'lowercase' => ['ABCDE12!!', 'at least 2 lowercase'];
        yield 'digits' => ['ABCabc!!!', 'at least 2 digits'];
        yield 'symbols' => ['ABCabc123', 'at least 2 symbols'];
    }

    #[DataProvider('invalidPasswordProvider')]
    public function testInvalidPasswords(string $password, string $messageFragment): void
    {
        $result = $this->policy()->validate($password);

        self::assertFalse($result->isValid());
        self::assertStringContainsString($messageFragment, $result->getErrorMessages()[0]);
    }

    public function testAcceptsExactMinimumCountsAndUnicodeCharacters(): void
    {
        self::assertTrue($this->policy()->validate('ÄÖäö12♥!x')->isValid());
    }

    public function testZeroDisablesCharacterRequirement(): void
    {
        $policy = new PasswordPolicy(new PasswordPolicyConfig(), $this->createTranslator());

        self::assertTrue($policy->validate('abcdef')->isValid());
    }

    public function testSingularMessagesUseSingularNouns(): void
    {
        $policy = new PasswordPolicy(
            new PasswordPolicyConfig(minLength: 1, minDigits: 1),
            $this->createTranslator(),
        );

        self::assertSame(
            'Password must contain at least 1 digit.',
            $policy->validate('abcdef')->getErrorMessages()[0],
        );
    }

    public function testReportsAllViolationsAndThrowsTypedException(): void
    {
        $policy = $this->policy();
        $result = $policy->validate('');

        self::assertCount(5, $result->getErrors());

        try {
            $policy->assertValid('');
            self::fail('Exception was not thrown.');
        } catch (PasswordPolicyViolationException $exception) {
            self::assertCount(5, $exception->getErrors());
            self::assertSame($exception->getErrors()[0], $exception->getMessage());
        }
    }

    private function policy(): PasswordPolicy
    {
        return new PasswordPolicy(
            new PasswordPolicyConfig(
                minLength: 8,
                maxLength: 9,
                minUppercase: 2,
                minLowercase: 2,
                minDigits: 2,
                minSymbols: 2,
            ),
            $this->createTranslator(),
        );
    }
}
