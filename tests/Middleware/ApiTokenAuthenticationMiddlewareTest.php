<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class ApiTokenAuthenticationMiddlewareTest extends TestCase
{
    public function testProcessOverridesIdentityAndDelegatesForValidToken(): void
    {
        $identity = $this->createMock(User::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->with('Authorization')->willReturn(['Bearer valid-token']);

        $identityRepository = $this->createMock(IdentityWithTokenRepositoryInterface::class);
        $identityRepository->expects(self::once())
            ->method('findIdentityByToken')
            ->with('valid-token', null)
            ->willReturn($identity);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('overrideIdentity')->with($identity);

        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            identityRepository: $identityRepository,
            currentUser: $currentUser,
        );

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessReturns401AndNeverDelegatesForInvalidToken(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->with('Authorization')->willReturn(['Bearer invalid-token']);

        $identityRepository = $this->createMock(IdentityWithTokenRepositoryInterface::class);
        $identityRepository->expects(self::once())->method('findIdentityByToken')->with('invalid-token', null)->willReturn(null);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::never())->method('overrideIdentity');

        $unauthorizedResponse = $this->createMock(ResponseInterface::class);
        $challengedResponse = $this->createMock(ResponseInterface::class);
        $unauthorizedResponse->expects(self::once())
            ->method('withHeader')
            ->with('WWW-Authenticate', 'Authorization realm="api"')
            ->willReturn($challengedResponse);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(401)->willReturn($unauthorizedResponse);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = $this->createMiddleware(
            identityRepository: $identityRepository,
            responseFactory: $responseFactory,
            currentUser: $currentUser,
        );

        $result = $middleware->process($request, $handler);

        self::assertSame($challengedResponse, $result);
    }

    public function testProcessReturns401WhenAuthorizationHeaderIsMissing(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->with('Authorization')->willReturn([]);

        $identityRepository = $this->createMock(IdentityWithTokenRepositoryInterface::class);
        $identityRepository->expects(self::never())->method('findIdentityByToken');

        $unauthorizedResponse = $this->createMock(ResponseInterface::class);
        $unauthorizedResponse->method('withHeader')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(401)->willReturn($unauthorizedResponse);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = $this->createMiddleware(
            identityRepository: $identityRepository,
            responseFactory: $responseFactory,
        );

        $middleware->process($request, $handler);
    }

    private function createMiddleware(
        ?IdentityWithTokenRepositoryInterface $identityRepository = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?CurrentUser $currentUser = null,
    ): ApiTokenAuthenticationMiddleware {
        return new ApiTokenAuthenticationMiddleware(
            $identityRepository ?? $this->createMock(IdentityWithTokenRepositoryInterface::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $currentUser ?? $this->createMock(CurrentUser::class),
        );
    }
}
