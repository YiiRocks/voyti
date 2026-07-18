<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeSession;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SessionRevocationEnforceMiddlewareTest extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testProcessLogsOutAndRedirectsWhenSessionRowMissing(): void
    {
        $user = $this->createUser();

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);
        $currentUser->expects(self::once())->method('logout');

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/profile-update');

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/session-login')->willReturn('/voyti/session-login');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('withHeader')->with('Location', '/voyti/session-login')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(302)->willReturn($response);

        $session = $this->createOpenSession('revoked-session-id');

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            currentRoute: $currentRoute,
            responseFactory: $responseFactory,
            session: $session,
            url: $url,
        );

        $middleware->process($request, $handler);
    }

    public function testProcessPassesThroughForExemptLoginRoute(): void
    {
        $user = $this->createUser();

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);
        $currentUser->expects(self::never())->method('logout');

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/session-login');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $session = $this->createOpenSession('unrecorded-session-id');

        $middleware = $this->createMiddleware(currentUser: $currentUser, currentRoute: $currentRoute, session: $session);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForExemptLogoutRoute(): void
    {
        $user = $this->createUser();

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);
        $currentUser->expects(self::never())->method('logout');

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/session-logout');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $session = $this->createOpenSession('unrecorded-session-id');

        $middleware = $this->createMiddleware(currentUser: $currentUser, currentRoute: $currentRoute, session: $session);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForGuestUser(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $guestIdentity = $this->createMock(GuestIdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($guestIdentity);

        $middleware = $this->createMiddleware(currentUser: $currentUser);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForNonUserIdentity(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $identity = $this->createMock(\Yiisoft\Auth\IdentityInterface::class);

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($identity);

        $middleware = $this->createMiddleware(currentUser: $currentUser);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenSessionIdIsNull(): void
    {
        $user = $this->createUser();

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);
        $currentUser->expects(self::never())->method('logout');

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/profile-update');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, currentRoute: $currentRoute, session: new FakeSession());
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenSessionRowExists(): void
    {
        $user = $this->createUser();

        $userSession = new UserSessions();
        $userSession->setUserId((int) $user->getId());
        $userSession->setSessionId('active-session-id');
        $userSession->setIp('127.0.0.1');
        $userSession->setCreatedAt(time() - 3600);
        $userSession->setUpdatedAt(time() - 3600);
        $userSession->save();

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects(self::once())->method('getIdentity')->willReturn($user);
        $currentUser->expects(self::never())->method('logout');

        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->method('getName')->willReturn('voyti/profile-update');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $session = $this->createOpenSession('active-session-id');

        $middleware = $this->createMiddleware(currentUser: $currentUser, currentRoute: $currentRoute, session: $session);
        $before = time();
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);

        $refreshed = UserSessions::findByUserIdAndSessionId((int) $user->getId(), 'active-session-id');
        self::assertNotNull($refreshed);
        self::assertGreaterThanOrEqual($before, $refreshed->getUpdatedAt());
    }

    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?CurrentRoute $currentRoute = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?FakeSession $session = null,
        ?UrlGeneratorInterface $url = null,
    ): SessionRevocationEnforceMiddleware {
        return new SessionRevocationEnforceMiddleware(
            $currentUser ?? $this->createMock(CurrentUser::class),
            $currentRoute ?? $this->createMock(CurrentRoute::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $session ?? new FakeSession(),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
        );
    }

    private function createOpenSession(string $id): FakeSession
    {
        $session = new FakeSession();
        $session->setId($id);
        $session->open();

        return $session;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('sessuser');
        $user->setEmail('sessuser@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
