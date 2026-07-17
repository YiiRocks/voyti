<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Session\SessionMiddleware;

final class RoutesTest extends TestCase
{

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
        $params = ['yiirocks/voyti' => $voytiParams];
        $routes = require dirname(__DIR__) . '/config/routes.php';

        $collector = new RouteCollector();
        $collector->addRoute(...$routes);

        return (new RouteCollection($collector))->getRoute($name);
    }
}
