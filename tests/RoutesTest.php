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
        // chillerlan/2fa-qrcode-bundle is a dev dependency of this package, so it's always
        // installed here; the "package missing" branch that omits these two routes can't be
        // exercised from this test (see QrCodeUriGeneratorService::isAvailable()).
        $route = $this->getRoute('voyti/user-two-factor-google', ['enableTwoFactorAuthentication' => true]);
        self::assertSame('settings/two-factor/google/', $route->getData('pattern'));

        $renewRoute = $this->getRoute('voyti/user-two-factor-google-renew', ['enableTwoFactorAuthentication' => true]);
        self::assertSame('settings/two-factor/google/renew', $renewRoute->getData('pattern'));
    }

    public function testOpenApiRouteIsPublic(): void
    {
        $route = $this->getApiRoute('voyti/api-openapi');
        $middlewares = $route->getData('enabledMiddlewares');

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
        $route = $this->getApiRoute('voyti/api-v1-users-index');

        self::assertContains(
            JsonDataResponseMiddleware::class,
            $route->getData('enabledMiddlewares'),
            'The REST API route group must format DataResponse bodies as JSON, otherwise reading '
            . 'the response body throws LogicException at request time (no formatter is applied '
            . 'without this middleware).',
        );
    }

    public function testRestApiRouteRequiresAdminAccess(): void
    {
        $route = $this->getApiRoute('voyti/api-v1-users-index');

        self::assertContains(AccessRuleMiddleware::class, $route->getData('enabledMiddlewares'));
    }

    public function testRestApiRouteUsesTokenAuthenticationInsteadOfSession(): void
    {
        $route = $this->getApiRoute('voyti/api-v1-users-index');
        $middlewares = $route->getData('enabledMiddlewares');

        self::assertContains(ApiTokenAuthenticationMiddleware::class, $middlewares);
        self::assertNotContains(
            SessionMiddleware::class,
            $middlewares,
            'The REST API must not rely on cookie/session auth (CSRF-exposed for state-changing '
            . 'requests); it authenticates via Bearer token instead.',
        );
    }

    private function getApiRoute(string $name): Route
    {
        $routes = require dirname(__DIR__) . '/config/routes-api.php';

        $collector = new RouteCollector();
        $collector->addRoute(...$routes);

        return (new RouteCollection($collector))->getRoute($name);
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
