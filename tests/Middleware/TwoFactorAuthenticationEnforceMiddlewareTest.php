<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentity;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TwoFactorAuthenticationEnforceMiddlewareTest extends TestCase
{

    public function testProcessDoesNotQueryRbacWhenNoForcedPermissions(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: [],
        );

        $user = $this->createUserWithId(1);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::never())->method('getPermissionsByUserId');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForGuestUser(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);

        $guestIdentity = new GuestIdentity();
        $currentUser = $this->createCurrentUser($guestIdentity);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForNonUserIdentity(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);

        $identity = $this->createMock(IdentityInterface::class);
        $currentUser = $this->createCurrentUser($identity);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForNonUserIdentityWithForcedPermissions(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $identity = $this->createMock(IdentityInterface::class);
        $currentUser = $this->createCurrentUser($identity);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->method('getPermissionsByUserId')->willReturn([
            'admin' => new Permission('admin'),
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhen2FADisabled(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: false);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhen2FADisabledDespiteForcedPermissions(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: false,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(42);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->method('getPermissionsByUserId')->willReturn([
            'admin' => new Permission('admin'),
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenNoForcedPermissions(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: [],
        );

        $user = $this->createUserWithId(1);
        $currentUser = $this->createCurrentUser($user);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenUserHasRequiredPermissionAnd2FAEnabled(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(42);
        $user->setAuthTfEnabled(true);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->with(42)->willReturn([
            'admin' => new Permission('admin'),
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenUserLacksRequiredPermissions(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(1);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->willReturn([
            'editor' => new Permission('editor'),
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessRedirectsWhenUserHasRequiredPermissionBut2FANotEnabled(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(42);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->with(42)->willReturn([
            'admin' => new Permission('admin'),
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/account-update')->willReturn('/voyti/account-update');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('withHeader')->with('Location', '/voyti/account-update')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(302)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
            responseFactory: $responseFactory,
            url: $url,
        );

        $middleware->process($request, $handler);
    }

    public function testProcessWithUserIdZero(): void
    {
        $config = new ModuleConfig(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(null);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->with(0)->willReturn([]);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }
    private function createCurrentUser(IdentityInterface $identity): CurrentUser
    {
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $currentUser = new CurrentUser($identityRepository, $eventDispatcher);
        $currentUser->overrideIdentity($identity);
        return $currentUser;
    }

    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?ModuleConfig $config = null,
        ?ManagerInterface $authManager = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?UrlGeneratorInterface $url = null,
    ): TwoFactorAuthenticationEnforceMiddleware {
        return new TwoFactorAuthenticationEnforceMiddleware(
            $currentUser ?? $this->createCurrentUser($this->createMock(IdentityInterface::class)),
            $config ?? new ModuleConfig(),
            $authManager ?? $this->createMock(ManagerInterface::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
        );
    }

    private function createUserWithId(?int $id): User
    {
        $user = new User();
        if ($id !== null) {
            $ref = new \ReflectionProperty(User::class, 'id');
            $ref->setValue($user, $id);
        }
        return $user;
    }
}
