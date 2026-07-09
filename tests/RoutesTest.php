<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Session\SessionMiddleware;

final class RoutesTest extends TestCase
{

    public function testRestApiGroupFormatsResponsesAsJson(): void
    {
        $apiGroup = $this->findApiGroup($this->loadRoutes(['enableRestApi' => true]));

        self::assertContains(
            JsonDataResponseMiddleware::class,
            $apiGroup->getData('enabledMiddlewares'),
            'The REST API route group must format DataResponse bodies as JSON, otherwise reading '
            . 'the response body throws LogicException at request time (no formatter is applied '
            . 'without this middleware).',
        );
    }
    public function testRestApiGroupIsNotRegisteredWhenDisabled(): void
    {
        $result = $this->loadRoutes(['enableRestApi' => false]);

        foreach ($result as $item) {
            self::assertNotSame('api/v1/', $item->getData('prefix'));
        }
    }

    public function testRestApiGroupRequiresAdminAccess(): void
    {
        $apiGroup = $this->findApiGroup($this->loadRoutes(['enableRestApi' => true]));

        self::assertContains(AccessRuleMiddleware::class, $apiGroup->getData('enabledMiddlewares'));
    }

    public function testRestApiGroupUsesConfiguredPrefix(): void
    {
        $apiGroup = $this->findApiGroup($this->loadRoutes(['enableRestApi' => true, 'adminRestPrefix' => 'custom/prefix']));

        self::assertSame('custom/prefix/v1/', $apiGroup->getData('prefix'));
    }

    public function testRestApiGroupUsesTokenAuthenticationInsteadOfSession(): void
    {
        $apiGroup = $this->findApiGroup($this->loadRoutes(['enableRestApi' => true]));
        $middlewares = $apiGroup->getData('enabledMiddlewares');

        self::assertContains(ApiTokenAuthenticationMiddleware::class, $middlewares);
        self::assertNotContains(
            SessionMiddleware::class,
            $middlewares,
            'The REST API must not rely on cookie/session auth (CSRF-exposed for state-changing '
            . 'requests); it authenticates via Bearer token instead.',
        );
    }

    private function findApiGroup(array $result): Group
    {
        foreach ($result as $item) {
            if ($item instanceof Group && $item->getData('prefix') !== null) {
                return $item;
            }
        }

        self::fail('REST API route group was not found.');
    }

    /**
     * @param array<string, mixed> $voytiParams
     *
     * @return array<Group>
     */
    private function loadRoutes(array $voytiParams): array
    {
        $params = ['yiirocks/voyti' => $voytiParams];

        return require dirname(__DIR__) . '/config/routes.php';
    }
}
