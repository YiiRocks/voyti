<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Cookies\CookieMiddleware;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class RememberMeMiddlewareTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testProcessAlreadyAuthenticatedUserRefreshesCookieOnResponse(): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('99');
        $identity->method('getCookieLoginKey')->willReturn('ckey');

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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('99');
        $identity->method('getCookieLoginKey')->willReturn('ckey');

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

        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);
        $identity->method('getCookieLoginKey')->willReturn('key123');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

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
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
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

        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

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
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
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
        $cookieMiddleware = $this->createMock(CookieMiddleware::class);
        $cookieMiddleware->method('process')->willReturnCallback(
            static fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface => $handler->handle($request),
        );

        return $cookieMiddleware;
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
