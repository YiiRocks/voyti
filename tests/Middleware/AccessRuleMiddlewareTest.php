<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AccessRuleMiddlewareTest extends TestCase
{

    public function testProcessPassesThroughForAdmin(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $identity = $this->createMock(IdentityInterface::class);
        $identity->expects(self::once())->method('getId')->willReturn('1');

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($identity);

        $authHelper = $this->createMock(AuthHelper::class);
        $authHelper->expects(self::once())->method('isAdmin')->with('1')->willReturn(true);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            authHelper: $authHelper,
        );

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessRedirectsGuestToLogin(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $guestIdentity = $this->createMock(GuestIdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($guestIdentity);

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
        $identity->expects(self::once())->method('getId')->willReturn('42');

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($identity);

        $authHelper = $this->createMock(AuthHelper::class);
        $authHelper->expects(self::once())->method('isAdmin')->with('42')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(403)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            authHelper: $authHelper,
            responseFactory: $responseFactory,
        );

        $middleware->process($request, $handler);
    }

    public function testProcessWithGuestNullId(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $guestIdentity = $this->createMock(GuestIdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($guestIdentity);

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
    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?AuthHelper $authHelper = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?UrlGeneratorInterface $url = null,
    ): AccessRuleMiddleware {
        return new AccessRuleMiddleware(
            $currentUser ?? $this->createMock(CurrentUser::class),
            $authHelper ?? $this->createMock(AuthHelper::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
        );
    }
}
