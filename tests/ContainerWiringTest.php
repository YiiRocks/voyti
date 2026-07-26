<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use Composer\InstalledVersions;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use Yiisoft\Cookies\CookieEncryptor;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\Flash;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
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

        $hydratorDiPath = InstalledVersions::getInstallPath('yiisoft/hydrator') . '/config/di.php';
        $definitions = array_merge(require $hydratorDiPath, $definitions);

        // Voyti binds no Collection/StateStorageInterface of its own - those come from
        // yii-auth-client's own config/di.php, which yiisoft/config auto-merges in for any host
        // that installs the (optional) package. Replicate that merge here so AuthAction's
        // dependency chain resolves the same way it would in a real application.
        $authClientInstallPath = InstalledVersions::getInstallPath('yiisoft/yii-auth-client');
        $authClientParams = require $authClientInstallPath . '/config/params.php';
        $authClientDiPath = $authClientInstallPath . '/config/di.php';
        $authClientDefinitions = (static function (array $params) use ($authClientDiPath): array {
            return require $authClientDiPath;
        })($authClientParams);
        $definitions = array_merge($authClientDefinitions, $definitions);

        $psr17Factory = new Psr17Factory();
        $session = new FakeSession();

        $definitions = array_merge($definitions, [
            AssignmentsStorageInterface::class => new SimpleAssignmentsStorage(),
            CookieEncryptor::class => new CookieEncryptor('test-secret-key-0123456789abcdef'),
            CookieSigner::class => new CookieSigner('test-secret-key-0123456789abcdef'),
            CurrentRoute::class => new CurrentRoute(),
            EventDispatcherInterface::class => new EventCaptureDispatcher(),
            FlashInterface::class => new Flash($session),
            ItemsStorageInterface::class => new SimpleItemsStorage(),
            LoggerInterface::class => new NullLogger(),
            MailerInterface::class => new MailCapture(),
            ManagerInterface::class => Manager::class,
            PsrClientInterface::class => $this->createMock(PsrClientInterface::class),
            RequestFactoryInterface::class => $psr17Factory,
            ResponseFactoryInterface::class => $psr17Factory,
            SessionInterface::class => $session,
            StreamFactoryInterface::class => $psr17Factory,
            TranslatorInterface::class => $this->createTranslator(),
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

    private function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(dirname(__DIR__) . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );
        return $translator;
    }
}
