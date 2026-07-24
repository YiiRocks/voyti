<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentity;

#[AllowMockObjectsWithoutExpectations]
final class TwoFactorAuthenticationEnforceMiddlewareTest extends TestCase
{
    public function testProcessDoesNotQueryRbacWhenNoForcedPermissions(): void
    {
        $config = ModuleConfigFactory::create(
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

    public function testProcessPassesThroughForExemptLogoutRoute(): void
    {
        $config = ModuleConfigFactory::create(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(42);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::never())->method('getPermissionsByUserId');

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/session-logout');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
            currentRoute: $currentRoute,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForExemptTwoFactorRoute(): void
    {
        $config = ModuleConfigFactory::create(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(42);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::never())->method('getPermissionsByUserId');

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/user-two-factor-enable');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
            currentRoute: $currentRoute,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForGuestUser(): void
    {
        $config = ModuleConfigFactory::create(enableTwoFactorAuthentication: true);

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
        $config = ModuleConfigFactory::create(enableTwoFactorAuthentication: true);

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
        $config = ModuleConfigFactory::create(
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
        $config = ModuleConfigFactory::create(enableTwoFactorAuthentication: false);

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
        $config = ModuleConfigFactory::create(
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

    public function testProcessPassesThroughWhenUserHasRequiredPermissionAnd2FAEnabled(): void
    {
        $config = ModuleConfigFactory::create(
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
        $config = ModuleConfigFactory::create(
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

    public function testProcessRedirectsToTwoFactorSettingsWithoutFlashServiceConfigured(): void
    {
        $config = ModuleConfigFactory::create(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(42);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->method('getPermissionsByUserId')->willReturn([
            'admin' => new Permission('admin'),
        ]);

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/admin');

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/user-two-factor')->willReturn('/voyti/user-two-factor');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('withHeader')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(302)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
            currentRoute: $currentRoute,
            responseFactory: $responseFactory,
            url: $url,
            flash: null,
        );

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessRedirectsWhenUserHasRequiredPermissionBut2FANotEnabled(): void
    {
        $config = ModuleConfigFactory::create(
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
        );

        $user = $this->createUserWithId(42);
        $currentUser = $this->createCurrentUser($user);

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->with(42)->willReturn([
            'admin' => new Permission('admin'),
        ]);

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/admin');

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/user-two-factor')->willReturn('/voyti/user-two-factor');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('withHeader')->with('Location', '/voyti/user-two-factor')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(302)->willReturn($response);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('set')->with(
            FlashType::WARNING,
            'Two-factor authentication is required for your account. Please enable it to continue.',
        );

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            authManager: $authManager,
            currentRoute: $currentRoute,
            responseFactory: $responseFactory,
            url: $url,
            flash: $flash,
        );

        $middleware->process($request, $handler);
    }

    public function testProcessWithUserIdZero(): void
    {
        $config = ModuleConfigFactory::create(
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
        ?CurrentRoute $currentRoute = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?TranslatorInterface $translator = null,
        ?UrlGeneratorInterface $url = null,
        ?FlashInterface $flash = null,
    ): TwoFactorAuthenticationEnforceMiddleware {
        return new TwoFactorAuthenticationEnforceMiddleware(
            $currentUser ?? $this->createCurrentUser($this->createMock(IdentityInterface::class)),
            $config ?? ModuleConfigFactory::create(),
            $authManager ?? $this->createMock(ManagerInterface::class),
            $currentRoute ?? $this->createMock(CurrentRoute::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $translator ?? $this->createTranslator(),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
            $flash,
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
