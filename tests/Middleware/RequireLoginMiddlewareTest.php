<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\RequireLoginMiddleware;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

#[AllowMockObjectsWithoutExpectations]
final class RequireLoginMiddlewareTest extends TestCase
{
    public function testProcessPassesThroughForAuthenticatedIdentity(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $identity = $this->createMock(IdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($identity);

        $middleware = $this->createMiddleware(currentUser: $currentUser);

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

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/user-account');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/session-login')->willReturn('/voyti/login');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('withHeader')->with('Location', '/voyti/login')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(302)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            currentRoute: $currentRoute,
            responseFactory: $responseFactory,
            url: $url,
        );

        $middleware->process($request, $handler);
    }

    public function testProcessReturnsJsonUnauthorizedForJsonResponseRoute(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $guestIdentity = $this->createMock(GuestIdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($guestIdentity);

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/user-two-factor-renew');

        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::once())
            ->method('write')
            ->with($this->callback(
                static fn(string $json): bool => json_decode($json, true) === ['error' => 'Not authenticated'],
            ));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($body);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(401)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            currentRoute: $currentRoute,
            responseFactory: $responseFactory,
        );

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?CurrentRoute $currentRoute = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?TranslatorInterface $translator = null,
        ?UrlGeneratorInterface $url = null,
    ): RequireLoginMiddleware {
        return new RequireLoginMiddleware(
            $currentUser ?? $this->createMock(CurrentUser::class),
            $currentRoute ?? $this->createMock(CurrentRoute::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $translator ?? $this->createTranslator(),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
        );
    }
}
