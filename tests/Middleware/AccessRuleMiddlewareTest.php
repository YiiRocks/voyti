<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class AccessRuleMiddlewareTest extends TestCase
{
    use CurrentUserTrait;

    public function testProcessPassesThroughForAdmin(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $identity = $this->createMock(IdentityInterface::class);
        $identity->method('getId')->willReturn('1');

        $currentUser = $this->createCurrentUser($identity);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            authHelper: $this->createAuthHelper(adminUserId: '1'),
        );

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessRedirectsGuestToLogin(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $currentUser = $this->createCurrentUser();

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/session-login')->willReturn('/voyti/login');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('withHeader')->with('Location', '/voyti/login')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(302)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            responseFactory: $responseFactory,
            url: $url,
        );

        $middleware->process($request, $handler);
    }

    public function testProcessReturns403ForNonAdmin(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $identity = $this->createMock(IdentityInterface::class);
        $identity->method('getId')->willReturn('42');

        $currentUser = $this->createCurrentUser($identity);

        $response = $this->createMock(ResponseInterface::class);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(403)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            authHelper: $this->createAuthHelper(),
            responseFactory: $responseFactory,
        );

        $middleware->process($request, $handler);
    }

    private function createAuthHelper(?string $adminUserId = null): AuthHelper
    {
        $config = VoytiConfigFactory::create();
        $itemsStorage = new SimpleItemsStorage();
        $assignmentsStorage = new SimpleAssignmentsStorage();
        $manager = new Manager($itemsStorage, $assignmentsStorage);

        if ($adminUserId !== null) {
            $itemsStorage->add(new Permission($config->administratorPermissionName));
            $itemsStorage->add(new Role('admin'));
            $manager->addChild('admin', $config->administratorPermissionName);
            $manager->assign('admin', $adminUserId);
        }

        return new AuthHelper($manager, $itemsStorage, $assignmentsStorage, $config, $this->createCurrentUser());
    }

    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?AuthHelper $authHelper = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?UrlGeneratorInterface $url = null,
    ): AccessRuleMiddleware {
        return new AccessRuleMiddleware(
            $currentUser ?? $this->createCurrentUser(),
            $authHelper ?? $this->createAuthHelper(),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
        );
    }
}
