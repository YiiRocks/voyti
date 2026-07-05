<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use InvalidArgumentException;
use ReflectionProperty;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Strategy\DefaultEmailChangeStrategy;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Strategy\InsecureEmailChangeStrategy;
use YiiRocks\Voyti\Strategy\MailChangeStrategyInterface;
use YiiRocks\Voyti\Strategy\SecureEmailChangeStrategy;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

final class EmailChangeStrategyFactoryTest extends \PHPUnit\Framework\TestCase
{

    public function testMakeByStrategyTypeReturnsDefaultStrategyWiredWithTokenFactoryAndMailService(): void
    {
        $tokenFactory = $this->tokenFactory();
        $mailService = $this->mailService();
        $factory = new EmailChangeStrategyFactory($tokenFactory, $mailService);
        $form = $this->form();

        $strategy = $factory->makeByStrategyType(MailChangeStrategyInterface::TYPE_DEFAULT, $form);

        self::assertInstanceOf(DefaultEmailChangeStrategy::class, $strategy);
        self::assertSame($form, $this->readProperty($strategy, 'form'));
        self::assertSame($tokenFactory, $this->readProperty($strategy, 'tokenFactory'));
        self::assertSame($mailService, $this->readProperty($strategy, 'mailService'));
    }

    public function testMakeByStrategyTypeReturnsInsecureStrategy(): void
    {
        $factory = $this->factory();
        $form = $this->form();

        $strategy = $factory->makeByStrategyType(MailChangeStrategyInterface::TYPE_INSECURE, $form);

        self::assertInstanceOf(InsecureEmailChangeStrategy::class, $strategy);
        self::assertSame($form, $this->readProperty($strategy, 'form'));
    }

    public function testMakeByStrategyTypeReturnsSecureStrategyWrappingDefaultStrategy(): void
    {
        $tokenFactory = $this->tokenFactory();
        $mailService = $this->mailService();
        $factory = new EmailChangeStrategyFactory($tokenFactory, $mailService);
        $form = $this->form();

        $strategy = $factory->makeByStrategyType(MailChangeStrategyInterface::TYPE_SECURE, $form);

        self::assertInstanceOf(SecureEmailChangeStrategy::class, $strategy);
        self::assertSame($form, $this->readProperty($strategy, 'form'));
        self::assertSame($tokenFactory, $this->readProperty($strategy, 'tokenFactory'));
        self::assertSame($mailService, $this->readProperty($strategy, 'mailService'));

        $inner = $this->readProperty($strategy, 'defaultStrategy');
        self::assertInstanceOf(DefaultEmailChangeStrategy::class, $inner);
        self::assertSame($form, $this->readProperty($inner, 'form'));
        self::assertSame($tokenFactory, $this->readProperty($inner, 'tokenFactory'));
        self::assertSame($mailService, $this->readProperty($inner, 'mailService'));
    }

    public function testMakeByStrategyTypeThrowsForUnknownNegativeStrategy(): void
    {
        $factory = $this->factory();

        $this->expectException(InvalidArgumentException::class);

        $factory->makeByStrategyType(-1, $this->form());
    }
    public function testMakeByStrategyTypeThrowsForUnknownStrategy(): void
    {
        $factory = $this->factory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown strategy type');

        $factory->makeByStrategyType(999, $this->form());
    }

    private function factory(): EmailChangeStrategyFactory
    {
        return new EmailChangeStrategyFactory($this->tokenFactory(), $this->mailService());
    }

    private function form(): SettingsForm
    {
        return new SettingsForm($this->createStub(TranslatorInterface::class));
    }

    private function mailService(): MailService
    {
        return new MailService(
            $this->createStub(MailerInterface::class),
            '/tmp/mail-views',
            $this->createStub(TranslatorInterface::class),
            $this->createStub(UrlGeneratorInterface::class),
        );
    }

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }

    private function tokenFactory(): UserTokenFactory
    {
        return new UserTokenFactory(new UserTokenRepository());
    }
}
