<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Cookies\CookieEncryptor;
use Yiisoft\Cookies\CookieMiddleware;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class RememberMeMiddlewareTest extends DatabaseTestCase
{
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

    public function testProcessAlreadyAuthenticatedUserRefreshesCookieOnResponse(): void
    {
        $identity = $this->createUser();

        $session = new FakeSession();
        $session->open();
        $currentUser = $this->createCurrentUser()->withSession($session);
        $currentUser->login($identity);
        $sessionId = $session->getId();
        self::assertNotNull($sessionId);

        $service = new RememberMeCookieService(3600, new SystemClock(), 'autoLogin');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $request = (new ServerRequest('GET', '/'));
        $handler = $this->createHandler(new Response());

        $middleware = new RememberMeMiddleware(
            $currentUser,
            $service,
            $identityRepository,
            $session,
            $this->createCookieMiddleware(),
        );

        $response = $middleware->process($request, $handler);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testProcessAlreadyAuthenticatedUserWithNoCookieDoesNotEmit(): void
    {
        $identity = $this->createUser();

        $session = new FakeSession();
        $session->open();
        $currentUser = $this->createCurrentUser()->withSession($session);
        $currentUser->login($identity);

        $service = new RememberMeCookieService(3600, new SystemClock(), 'autoLogin');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $middleware = new RememberMeMiddleware(
            $currentUser,
            $service,
            $identityRepository,
            $session,
            $this->createCookieMiddleware(),
        );

        $request = new ServerRequest('GET', '/');
        $response = new Response();
        $handler = $this->createHandler($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessGuestWithInvalidCookieDoesNotReissue(): void
    {
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser()->withSession($session);
        $service = new RememberMeCookieService(
            3600,
            new SystemClock(),
            'autoLogin',
            eventDispatcher: new EventCaptureDispatcher(),
        );
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn(null);

        $middleware = new RememberMeMiddleware(
            $currentUser,
            $service,
            $identityRepository,
            $session,
            $this->createCookieMiddleware(),
        );

        $request = (new ServerRequest('GET', '/'))->withCookieParams(['autoLogin' => 'not-json']);
        $response = new Response();
        $handler = $this->createHandler($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessGuestWithNoCookiePassesThroughUnchanged(): void
    {
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser()->withSession($session);
        $service = new RememberMeCookieService(
            3600,
            new SystemClock(),
            'autoLogin',
            eventDispatcher: new EventCaptureDispatcher(),
        );
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $middleware = new RememberMeMiddleware(
            $currentUser,
            $service,
            $identityRepository,
            $session,
            $this->createCookieMiddleware(),
        );

        $response = new Response();
        $handler = $this->createHandler($response);

        $result = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame($response, $result);
        self::assertSame('', $result->getHeaderLine('Set-Cookie'));
    }

    public function testProcessGuestWithValidCookieReissuesCookieOnResponse(): void
    {
        $user = $this->createUser(
            username: 'remembermiddleware' . random_int(1, 1000000),
            email: 'remembermiddleware' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user);

        $session = new FakeSession();
        $session->setId('cookie-session-id');
        $session->open();
        $currentUser = $this->createCurrentUser()->withSession($session);
        $service = new RememberMeCookieService(
            3600,
            new SystemClock(),
            'autoLogin',
            eventDispatcher: new EventCaptureDispatcher(),
        );

        $middleware = new RememberMeMiddleware(
            $currentUser,
            $service,
            $identityRepository,
            $session,
            $this->createCookieMiddleware(),
        );

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'cookie-session-id']);
        $request = (new ServerRequest('GET', '/'))->withCookieParams(['autoLogin' => $cookie]);

        $response = new Response();
        $handler = $this->createHandler($response);

        $result = $middleware->process($request, $handler);

        self::assertNotSame($response, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $decoded = json_decode(urldecode($matches[1]), true);
        self::assertNotSame('cookie-session-id', $decoded[3]);
        self::assertSame($session->getId(), $decoded[3]);
    }

    public function testProcessRotatedButIdentityChangedDuringHandleReturnsResponseUnchanged(): void
    {
        $user = $this->createUser(
            username: 'remembermiddleware' . random_int(1, 1000000),
            email: 'remembermiddleware' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user);

        $session = new FakeSession();
        $session->setId('cookie-session-id');
        $session->open();
        $currentUser = $this->createCurrentUser()->withSession($session);
        $service = new RememberMeCookieService(
            3600,
            new SystemClock(),
            'autoLogin',
            eventDispatcher: new EventCaptureDispatcher(),
        );

        $middleware = new RememberMeMiddleware(
            $currentUser,
            $service,
            $identityRepository,
            $session,
            $this->createCookieMiddleware(),
        );

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'cookie-session-id']);
        $request = (new ServerRequest('GET', '/'))->withCookieParams(['autoLogin' => $cookie]);

        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(static function () use ($currentUser, $response): ResponseInterface {
            // Simulate the request handler logging the auto-logged-in user back out
            // (e.g. hitting the logout route) before the middleware gets to reissue the cookie.
            $currentUser->logout();
            return $response;
        });

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    private function createCookieMiddleware(): CookieMiddleware
    {
        return new CookieMiddleware(
            new NullLogger(),
            new CookieEncryptor('test-secret-key-0123456789abcdef'),
            new CookieSigner('test-secret-key-0123456789abcdef'),
        );
    }

    private function createCurrentUser(): CurrentUser
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        return new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $eventDispatcher,
        );
    }

    private function createHandler(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }
}
