<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Controller\Admin\Dashboard\DashboardController;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Session\SessionMiddleware;

final class RoutesTest extends TestCase
{
    public function testAdminIndexRouteEnforcesTwoFactorAuthenticationWhenEnabled(): void
    {
        $route = $this->getRoute('voyti/admin', ['enableTwoFactorAuthentication' => true]);

        self::assertContains(TwoFactorAuthenticationEnforceMiddleware::class, $route->getData('enabledMiddlewares'));
    }

    public function testAdminIndexRouteRendersDashboard(): void
    {
        $route = $this->getRoute('voyti/admin', []);
        $middlewares = $route->getData('enabledMiddlewares');

        self::assertSame('admin/', $route->getData('pattern'));
        self::assertSame([DashboardController::class, 'index'], end($middlewares));
    }

    public function testAdminIndexRouteSkipsTwoFactorEnforcementWhenDisabled(): void
    {
        $route = $this->getRoute('voyti/admin', ['enableTwoFactorAuthentication' => false]);

        self::assertNotContains(TwoFactorAuthenticationEnforceMiddleware::class, $route->getData('enabledMiddlewares'));
    }

    public function testGoogleTwoFactorRoutesAreRegisteredWhenLibraryIsAvailable(): void
    {
        // chillerlan/php-authenticator and chillerlan/php-qrcode are dev dependencies of this
        // package, so they're always installed here; the "either package missing" branch that
        // omits these two routes can't be exercised from this test (see
        // QrCodeUriGeneratorService::isAvailable()).
        $route = $this->getRoute('voyti/user-two-factor-google', ['enableTwoFactorAuthentication' => true]);
        self::assertSame('settings/two-factor/google/', $route->getData('pattern'));

        $renewRoute = $this->getRoute('voyti/user-two-factor-google-renew', ['enableTwoFactorAuthentication' => true]);
        self::assertSame('settings/two-factor/google/renew', $renewRoute->getData('pattern'));
    }

    public function testOpenApiRouteIsPublic(): void
    {
        $route = $this->getRoute('voyti/api-openapi', ['enableRestApi' => true]);
        $middlewares = $route->getData('enabledMiddlewares');

        self::assertSame('api/openapi.json', $route->getData('pattern'));
        self::assertContains(
            JsonDataResponseMiddleware::class,
            $middlewares,
            'The OpenAPI spec must still be returned as JSON.',
        );
        self::assertNotContains(
            ApiTokenAuthenticationMiddleware::class,
            $middlewares,
            'OpenAPI/Swagger spec endpoints are conventionally public so tooling can fetch the schema '
            . 'without credentials; requiring a Bearer token here would be a regression.',
        );
        self::assertNotContains(AccessRuleMiddleware::class, $middlewares);
    }

    public function testRestApiRouteFormatsResponsesAsJson(): void
    {
        $route = $this->getRoute('voyti/api-v1-users-index', ['enableRestApi' => true]);

        self::assertContains(
            JsonDataResponseMiddleware::class,
            $route->getData('enabledMiddlewares'),
            'The REST API route group must format DataResponse bodies as JSON, otherwise reading '
            . 'the response body throws LogicException at request time (no formatter is applied '
            . 'without this middleware).',
        );
    }

    public function testRestApiRouteIsNotRegisteredWhenDisabled(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $this->getRoute('voyti/api-v1-users-index', ['enableRestApi' => false]);
    }

    public function testRestApiRouteRequiresAdminAccess(): void
    {
        $route = $this->getRoute('voyti/api-v1-users-index', ['enableRestApi' => true]);

        self::assertContains(AccessRuleMiddleware::class, $route->getData('enabledMiddlewares'));
    }

    public function testRestApiRouteUsesConfiguredPrefix(): void
    {
        $route = $this->getRoute('voyti/api-v1-users-index', ['enableRestApi' => true, 'adminRestPrefix' => 'custom/prefix']);

        self::assertSame('custom/prefix/v1/users', $route->getData('pattern'));
    }

    public function testRestApiRouteUsesTokenAuthenticationInsteadOfSession(): void
    {
        $route = $this->getRoute('voyti/api-v1-users-index', ['enableRestApi' => true]);
        $middlewares = $route->getData('enabledMiddlewares');

        self::assertContains(ApiTokenAuthenticationMiddleware::class, $middlewares);
        self::assertNotContains(
            SessionMiddleware::class,
            $middlewares,
            'The REST API must not rely on cookie/session auth (CSRF-exposed for state-changing '
            . 'requests); it authenticates via Bearer token instead.',
        );
    }

    /**
     * @param array<string, mixed> $voytiParams
     */
    private function getRoute(string $name, array $voytiParams): Route
    {
        $defaults = require dirname(__DIR__) . '/config/params.php';
        $params = ['yiirocks/voyti' => [...$defaults['yiirocks/voyti'], ...$voytiParams]];
        $routes = require dirname(__DIR__) . '/config/routes.php';

        $collector = new RouteCollector();
        $collector->addRoute(...$routes);

        return (new RouteCollection($collector))->getRoute($name);
    }
}
