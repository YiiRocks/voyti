<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class ApiTokenAuthenticationMiddlewareTest extends TestCase
{
    use CurrentUserTrait;

    public function testProcessOverridesIdentityAndDelegatesForValidToken(): void
    {
        $identity = new User();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->with('Authorization')->willReturn(['Bearer valid-token']);

        $identityRepository = $this->createMock(IdentityWithTokenRepositoryInterface::class);
        $identityRepository->expects(self::once())
            ->method('findIdentityByToken')
            ->with('valid-token', null)
            ->willReturn($identity);

        $currentUser = $this->createCurrentUser();

        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(
            identityRepository: $identityRepository,
            currentUser: $currentUser,
        );

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testProcessReturns401AndNeverDelegatesForInvalidToken(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->with('Authorization')->willReturn(['Bearer invalid-token']);

        $identityRepository = $this->createMock(IdentityWithTokenRepositoryInterface::class);
        $identityRepository->expects(self::once())->method('findIdentityByToken')->with('invalid-token', null)->willReturn(null);

        $currentUser = $this->createCurrentUser();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(401)->willReturn(new Response(401));

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = $this->createMiddleware(
            identityRepository: $identityRepository,
            responseFactory: $responseFactory,
            currentUser: $currentUser,
        );

        $result = $middleware->process($request, $handler);

        self::assertSame(401, $result->getStatusCode());
        self::assertStringContainsString('Bearer realm="api"', (string)($result->getHeader('WWW-Authenticate')[0] ?? ''));
        self::assertTrue($currentUser->isGuest());
    }

    private function createMiddleware(
        ?IdentityWithTokenRepositoryInterface $identityRepository = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?CurrentUser $currentUser = null,
    ): ApiTokenAuthenticationMiddleware {
        return new ApiTokenAuthenticationMiddleware(
            $identityRepository ?? $this->createMock(IdentityWithTokenRepositoryInterface::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $currentUser ?? $this->createCurrentUser(),
        );
    }
}
