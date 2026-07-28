<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Composer\InstalledVersions;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Builds a fresh PSR-11 DI container per test from config/di.php with
 * in-memory test fakes overlaid. Tests call getTestContainer() to resolve
 * services; per-test overrides are passed as an array merged on top.
 */
trait TestContainerTrait
{
    private static ?ContainerInterface $sharedTestContainer = null;

    /**
     * Build a fresh container with standard test fakes and optional per-test overrides.
     *
     * @param array<class-string, object|class-string|callable> $overrides Definitions merged on top of defaults.
     */
    protected function createTestContainer(array $overrides = []): ContainerInterface
    {
        $root = dirname(__DIR__, 2);
        $params = require $root . '/config/params.php';
        $diPath = $root . '/config/di.php';
        $definitions = (static function (array $params) use ($diPath): array {
            return require $diPath;
        })($params);

        $hydratorDiPath = InstalledVersions::getInstallPath('yiisoft/hydrator') . '/config/di.php';
        $definitions = array_merge(require $hydratorDiPath, $definitions);

        $psr17Factory = new Psr17Factory();

        $definitions = array_merge($definitions, [
            AssignmentsStorageInterface::class => new SimpleAssignmentsStorage(),
            CookieEncryptor::class => new CookieEncryptor('test-secret-key-0123456789abcdef'),
            CookieSigner::class => new CookieSigner('test-secret-key-0123456789abcdef'),
            CurrentRoute::class => new CurrentRoute(),
            EventDispatcherInterface::class => new EventCaptureDispatcher(),
            ItemsStorageInterface::class => new SimpleItemsStorage(),
            LoggerInterface::class => new NullLogger(),
            MailerInterface::class => new MailCapture(),
            ManagerInterface::class => Manager::class,
            PsrClientInterface::class => new class implements PsrClientInterface {
                public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    throw new \RuntimeException('HTTP client not configured in tests');
                }
            },
            RequestFactoryInterface::class => $psr17Factory,
            ResponseFactoryInterface::class => $psr17Factory,
            SessionInterface::class => new FakeSession(),
            StreamFactoryInterface::class => $psr17Factory,
            TranslatorInterface::class => (static function (): TranslatorInterface {
                $translator = new Translator('en', null, 'voyti');
                $translator->addCategorySources(
                    new CategorySource(
                        'voyti',
                        new MessageSource(dirname(__DIR__, 2) . '/resources/messages'),
                        new SimpleMessageFormatter(),
                    ),
                );

                return $translator;
            })(),
            UrlGeneratorInterface::class => new FakeUrlGenerator(),
        ]);

        $definitions = array_merge($definitions, $overrides);

        return new Container(ContainerConfig::create()->withDefinitions($definitions));
    }

    /**
     * Get or create the shared test container for this test method.
     *
     * When called with overrides, a fresh container is always built.
     * When called without overrides, the cached container is returned.
     *
     * @param array<class-string, object|class-string|callable> $overrides Definitions merged on top of defaults.
     */
    protected function getTestContainer(array $overrides = []): ContainerInterface
    {
        if (self::$sharedTestContainer === null || $overrides !== []) {
            self::$sharedTestContainer = $this->createTestContainer($overrides);
        }

        return self::$sharedTestContainer;
    }

    protected static function resetTestContainer(): void
    {
        self::$sharedTestContainer = null;
    }

}
