<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Strategy\BothEmailChangeStrategy;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Strategy\NewEmailChangeStrategy;
use YiiRocks\Voyti\Strategy\NoneEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class EmailChangeStrategyFactoryTest extends TestCase
{

    public function testMakeByStrategyTypeReturnsBothForBoth(): void
    {
        $factory = $this->createFactory();
        $strategy = $factory->makeByStrategyType(
            EmailChangeConfirmation::BOTH,
            new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)),
        );
        $this->assertInstanceOf(BothEmailChangeStrategy::class, $strategy);
    }

    public function testMakeByStrategyTypeReturnsNewForNew(): void
    {
        $factory = $this->createFactory();
        $strategy = $factory->makeByStrategyType(
            EmailChangeConfirmation::NEW,
            new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)),
        );
        $this->assertInstanceOf(NewEmailChangeStrategy::class, $strategy);
    }

    public function testMakeByStrategyTypeReturnsNoneForNone(): void
    {
        $factory = $this->createFactory();
        $strategy = $factory->makeByStrategyType(
            EmailChangeConfirmation::NONE,
            new SettingsForm(new ModuleConfig(), $this->createMock(TranslatorInterface::class)),
        );
        $this->assertInstanceOf(NoneEmailChangeStrategy::class, $strategy);
    }
    private function createFactory(): EmailChangeStrategyFactory
    {
        $tokenFactory = new UserTokenFactory();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $id) => $id);
        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService($mailCapture, '/tmp', $translator, $urlGenerator, 'App');

        return new EmailChangeStrategyFactory($tokenFactory, $mailService);
    }
}
