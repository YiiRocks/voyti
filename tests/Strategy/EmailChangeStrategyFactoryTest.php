<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Strategy\DefaultEmailChangeStrategy;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Strategy\InsecureEmailChangeStrategy;
use YiiRocks\Voyti\Strategy\MailChangeStrategyInterface;
use YiiRocks\Voyti\Strategy\SecureEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class EmailChangeStrategyFactoryTest extends TestCase
{

    public function testMakeByStrategyTypeReturnsDefaultForOne(): void
    {
        $factory = $this->createFactory();
        $strategy = $factory->makeByStrategyType(
            MailChangeStrategyInterface::TYPE_DEFAULT,
            new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)),
        );
        $this->assertInstanceOf(DefaultEmailChangeStrategy::class, $strategy);
    }

    public function testMakeByStrategyTypeReturnsInsecureForZero(): void
    {
        $factory = $this->createFactory();
        $strategy = $factory->makeByStrategyType(
            MailChangeStrategyInterface::TYPE_INSECURE,
            new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)),
        );
        $this->assertInstanceOf(InsecureEmailChangeStrategy::class, $strategy);
    }

    public function testMakeByStrategyTypeReturnsSecureForTwo(): void
    {
        $factory = $this->createFactory();
        $strategy = $factory->makeByStrategyType(
            MailChangeStrategyInterface::TYPE_SECURE,
            new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)),
        );
        $this->assertInstanceOf(SecureEmailChangeStrategy::class, $strategy);
    }

    public function testMakeByStrategyTypeThrowsForNegative(): void
    {
        $factory = $this->createFactory();

        $this->expectException(InvalidArgumentException::class);

        $factory->makeByStrategyType(-1, new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)));
    }

    public function testMakeByStrategyTypeThrowsForUnknown(): void
    {
        $factory = $this->createFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown strategy type');

        $factory->makeByStrategyType(999, new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)));
    }
    private function createFactory(): EmailChangeStrategyFactory
    {
        $tokenFactory = new UserTokenFactory(new UserTokenRepository());
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $id) => $id);
        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService($mailCapture, '/tmp', $translator, $urlGenerator, 'App');

        return new EmailChangeStrategyFactory($tokenFactory, $mailService);
    }
}
