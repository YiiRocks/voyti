<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

#[AllowMockObjectsWithoutExpectations]
final class PasswordAgeEnforceMiddlewareTest extends TestCase
{
    public function testProcessPassesThroughForExemptAccountSettingsRoute(): void
    {
        $config = new ModuleConfig(
            enablePasswordExpiration: true,
            maxPasswordAge: 90,
        );

        $user = new User();
        $user->setPasswordChangedAt(time() - 91 * 86400);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/account-update');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config, currentRoute: $currentRoute);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }
    public function testProcessPassesThroughForExemptLogoutRoute(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true, maxPasswordAge: 90);

        $user = new User();
        $user->setPasswordChangedAt(time() - 91 * 86400);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/session-logout');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config, currentRoute: $currentRoute);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForGuestUser(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true, maxPasswordAge: 90);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $guestIdentity = $this->createMock(GuestIdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($guestIdentity);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForNonUserIdentity(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true, maxPasswordAge: 90);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $identity = $this->createMock(IdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($identity);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenExpirationDisabledEvenIfPasswordVeryOld(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false, maxPasswordAge: 90);

        $user = new User();
        $user->setPasswordChangedAt(time() - 9999 * 86400);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenMaxPasswordAgeIsNull(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true, maxPasswordAge: null);

        $user = new User();
        $user->setPasswordChangedAt(time() - 9999 * 86400);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenPasswordNotExpired(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true, maxPasswordAge: 90);

        $user = new User();
        $user->setPasswordChangedAt(time());

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessRedirectsWhenPasswordExpired(): void
    {
        $config = new ModuleConfig(
            enablePasswordExpiration: true,
            maxPasswordAge: 90,
        );

        $user = new User();
        $user->setPasswordChangedAt(time() - 91 * 86400);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);

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
            responseFactory: $responseFactory,
            url: $url,
        );

        $middleware->process($request, $handler);
    }

    public function testProcessRedirectsWhenPasswordNeverChanged(): void
    {
        $config = new ModuleConfig(
            enablePasswordExpiration: true,
            maxPasswordAge: 90,
        );

        $user = new User();

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);

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
            responseFactory: $responseFactory,
            url: $url,
        );

        $middleware->process($request, $handler);
    }
    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?ModuleConfig $config = null,
        ?CurrentRoute $currentRoute = null,
        ?TranslatorInterface $translator = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?UrlGeneratorInterface $url = null,
    ): PasswordAgeEnforceMiddleware {
        $config ??= new ModuleConfig();

        return new PasswordAgeEnforceMiddleware(
            $currentUser ?? $this->createMock(CurrentUser::class),
            new ExpireService($config),
            $currentRoute ?? $this->createMock(CurrentRoute::class),
            $translator ?? $this->createTranslator(),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
        );
    }
}
