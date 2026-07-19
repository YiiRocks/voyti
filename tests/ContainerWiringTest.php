<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Unlike the rest of the suite (see CLAUDE.md: "Tests do not boot the DI
 * container"), this test builds a real Yiisoft\Di\Container from config/di.php
 * to catch wiring bugs (bad bindings, unresolvable constructor args) that
 * ControllerHarness's manual object graph can't surface.
 */
#[AllowMockObjectsWithoutExpectations]
final class ContainerWiringTest extends TestCase
{
    public function testEveryDiDefinitionResolves(): void
    {
        $root = dirname(__DIR__);
        $params = require $root . '/config/params.php';
        $diPath = $root . '/config/di.php';
        $definitions = (static function (array $params) use ($diPath): array {
            return require $diPath;
        })($params);

        $psr17Factory = new Psr17Factory();

        $definitions = array_merge($definitions, [
            AssignmentsStorageInterface::class => new SimpleAssignmentsStorage(),
            CurrentRoute::class => new CurrentRoute(),
            EventDispatcherInterface::class => new EventCaptureDispatcher(),
            ItemsStorageInterface::class => new SimpleItemsStorage(),
            MailerInterface::class => new MailCapture(),
            ManagerInterface::class => Manager::class,
            PsrClientInterface::class => $this->createMock(PsrClientInterface::class),
            RequestFactoryInterface::class => $psr17Factory,
            ResponseFactoryInterface::class => $psr17Factory,
            SessionInterface::class => new FakeSession(),
            StreamFactoryInterface::class => $psr17Factory,
            TranslatorInterface::class => $this->createMock(TranslatorInterface::class),
            UrlGeneratorInterface::class => new FakeUrlGenerator(),
        ]);

        $container = new Container(ContainerConfig::create()->withDefinitions($definitions));

        $failures = [];
        foreach (array_keys($definitions) as $id) {
            try {
                $container->get($id);
            } catch (Throwable $e) {
                $failures[] = sprintf('%s: %s', $id, $e->getMessage());
            }
        }

        self::assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * Regression test: host applications that haven't wired up a PSR-14
     * EventDispatcherInterface must still be able to resolve
     * RememberMeCookieService (it dispatches AfterLoginEvent only when one
     * is available - see RememberMeCookieService::loginByCookie()).
     */
    public function testRememberMeCookieServiceResolvesWithoutEventDispatcherBound(): void
    {
        $root = dirname(__DIR__);
        $params = require $root . '/config/params.php';
        $diPath = $root . '/config/di.php';
        $definitions = (static function (array $params) use ($diPath): array {
            return require $diPath;
        })($params);

        $container = new Container(ContainerConfig::create()->withDefinitions([
            ModuleConfig::class => $definitions[ModuleConfig::class],
            ClockInterface::class => $definitions[ClockInterface::class],
            RememberMeCookieService::class => $definitions[RememberMeCookieService::class],
        ]));

        self::assertInstanceOf(RememberMeCookieService::class, $container->get(RememberMeCookieService::class));
    }
}
