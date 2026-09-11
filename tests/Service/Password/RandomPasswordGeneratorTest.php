<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use YiiRocks\Voyti\PasswordPolicyConfig;
use YiiRocks\Voyti\Service\Password\PasswordPolicy;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;

final class RandomPasswordGeneratorTest extends TestCase
{
    public function testGeneratesPolicyCompliantPasswordAndClampsLength(): void
    {
        $policyConfig = new PasswordPolicyConfig(
            minLength: 10,
            maxLength: 12,
            minUppercase: 2,
            minLowercase: 2,
            minDigits: 2,
            minSymbols: 2,
        );
        $config = VoytiConfigFactory::create(passwordPolicy: $policyConfig);
        $generator = new RandomPasswordGenerator($config);
        $policy = new PasswordPolicy($policyConfig, $this->createTranslator());

        $minimum = $generator->generate(4);
        $maximum = $generator->generate(20);

        self::assertSame(10, strlen($minimum));
        self::assertTrue($policy->validate($minimum)->isValid());
        self::assertSame(12, strlen($maximum));
        self::assertTrue($policy->validate($maximum)->isValid());
    }

    public function testGeneratesEveryRequiredCharacterAtTheMaximumLength(): void
    {
        $policyConfig = new PasswordPolicyConfig(
            minLength: 1,
            maxLength: 6,
            minUppercase: 1,
            minLowercase: 1,
            minDigits: 2,
            minSymbols: 2,
        );
        $generator = new RandomPasswordGenerator(VoytiConfigFactory::create(passwordPolicy: $policyConfig));

        $password = $generator->generate(1);

        self::assertSame(6, strlen($password));
        self::assertMatchesRegularExpression('/\p{Lu}/u', $password);
        self::assertMatchesRegularExpression('/\p{Ll}/u', $password);
        self::assertSame(2, preg_match_all('/\p{Nd}/u', $password));
        self::assertSame(2, preg_match_all('/[^\p{L}\p{N}]/u', $password));
    }

    public function testDefaultFillerUsesEveryCharacterClass(): void
    {
        $generator = new RandomPasswordGenerator(VoytiConfigFactory::create());
        $passwords = '';

        for ($index = 0; $index < 20; $index++) {
            $passwords .= $generator->generate(72);
        }

        self::assertMatchesRegularExpression('/\p{Lu}/u', $passwords);
        self::assertMatchesRegularExpression('/\p{Ll}/u', $passwords);
        self::assertMatchesRegularExpression('/\p{Nd}/u', $passwords);
        self::assertMatchesRegularExpression('/[^\p{L}\p{N}]/u', $passwords);
    }
}
