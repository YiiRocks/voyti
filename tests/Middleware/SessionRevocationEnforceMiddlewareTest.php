<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\CurrentRouteTrait;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class SessionRevocationEnforceMiddlewareTest extends DatabaseTestCase
{
    use CurrentRouteTrait;
    use CurrentUserTrait;
    use UserFactoryTrait;

    public static function exemptRouteProvider(): iterable
    {
        yield 'login' => ['voyti/session-login'];
        yield 'logout' => ['voyti/session-logout'];
    }

    public function testProcessLogsOutAndRedirectsWhenSessionRowMissing(): void
    {
        $user = $this->createUser('sessuser', 'sessuser@example.com');

        $currentUser = $this->createCurrentUser($user);

        $currentRoute = $this->createCurrentRoute('voyti/user-profile');

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/session-login')->willReturn('/voyti/session-login');

        $session = $this->createOpenSession('revoked-session-id');

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            currentRoute: $currentRoute,
            session: $session,
            url: $url,
        );

        $result = $middleware->process($request, $handler);

        self::assertTrue($currentUser->isGuest());
        self::assertSame('/voyti/session-login', $result->getHeaderLine('Location'));
        self::assertStringContainsString('autoLogin', $result->getHeaderLine('Set-Cookie'));
    }

    public function testProcessLogsOutAndRedirectsWhenSessionRowRevoked(): void
    {
        $user = $this->createUser('revokeduser', 'revokeduser@example.com');

        $userSession = new UserSessions();
        $userSession->setUserId((int) $user->getId());
        $userSession->setSessionId('revoked-session-id');
        $userSession->setIp('127.0.0.1');
        $userSession->setCreatedAt(time());
        $userSession->setUpdatedAt(time());
        $userSession->setRevokedAt(time());
        $userSession->save();

        $currentUser = $this->createCurrentUser($user);

        $currentRoute = $this->createCurrentRoute('voyti/user-profile');

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/session-login')->willReturn('/voyti/session-login');

        $session = $this->createOpenSession('revoked-session-id');

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            currentRoute: $currentRoute,
            session: $session,
            url: $url,
        );

        $result = $middleware->process($request, $handler);

        self::assertTrue($currentUser->isGuest());
        self::assertSame('/voyti/session-login', $result->getHeaderLine('Location'));
        self::assertStringContainsString('autoLogin', $result->getHeaderLine('Set-Cookie'));
    }

    #[DataProvider('exemptRouteProvider')]
    public function testProcessPassesThroughForExemptRoute(string $routeName): void
    {
        $user = $this->createUser('sessuser', 'sessuser@example.com');

        $currentUser = $this->createCurrentUser($user);

        $currentRoute = $this->createCurrentRoute($routeName);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $session = $this->createOpenSession('unrecorded-session-id');

        $middleware = $this->createMiddleware(currentUser: $currentUser, currentRoute: $currentRoute, session: $session);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForNonUserIdentity(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $currentUser = $this->createCurrentUser($this->createMock(IdentityInterface::class));

        // A non-exempt route and an open session ensure the non-User guard must short-circuit before the
        // User-only session lookup - otherwise the middleware would call getIdOrZero() on a non-User identity.
        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            currentRoute: $this->createCurrentRoute('voyti/user-profile'),
            session: $this->createOpenSession('some-session-id'),
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenSessionIdIsNull(): void
    {
        $user = $this->createUser('sessuser', 'sessuser@example.com');

        $currentUser = $this->createCurrentUser($user);

        $currentRoute = $this->createCurrentRoute('voyti/user-profile');

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
        $user = $this->createUser('sessuser', 'sessuser@example.com');

        $userSession = new UserSessions();
        $userSession->setUserId((int) $user->getId());
        $userSession->setSessionId('active-session-id');
        $userSession->setIp('127.0.0.1');
        $userSession->setCreatedAt(time() - 3600);
        $userSession->setUpdatedAt(time() - 3600);
        $userSession->save();

        $currentUser = $this->createCurrentUser($user);

        $currentRoute = $this->createCurrentRoute('voyti/user-profile');

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
            $currentUser ?? $this->createCurrentUser(),
            $currentRoute ?? $this->createCurrentRoute(),
            new RememberMeCookieService(3600, new SystemClock()),
            $responseFactory ?? new Psr17Factory(),
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
}
