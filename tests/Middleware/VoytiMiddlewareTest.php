<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\VoytiMiddleware;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\CurrentRouteTrait;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Cookies\CookieEncryptor;
use Yiisoft\Cookies\CookieMiddleware;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class VoytiMiddlewareTest extends DatabaseTestCase
{
    use CurrentRouteTrait;
    use CurrentUserTrait;
    use UserFactoryTrait;

    public static function shortCircuitMiddlewareProvider(): iterable
    {
        yield 'rememberMe' => ['rememberMe'];
        yield 'first' => ['first'];
        yield 'second' => ['second'];
        yield 'third' => ['third'];
    }

    public function testProcessCallsAllFourMiddlewaresInOrder(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $rememberMe = $this->createPassThroughMiddleware();
        $first = $this->createPassThroughMiddleware();
        $second = $this->createPassThroughMiddleware();
        $third = $this->createPassThroughMiddleware();

        $middleware = new VoytiMiddleware(
            $rememberMe,
            $first,
            $second,
            $third,
        );
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessExecutesMiddlewaresInDeclaredOrder(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $log = [];
        $middleware = new VoytiMiddleware(
            $this->createRecordingMiddleware('rememberMe', $log),
            $this->createRecordingMiddleware('sessionRevocation', $log),
            $this->createRecordingMiddleware('passwordAge', $log),
            $this->createRecordingMiddleware('twoFactorAuth', $log),
        );

        $middleware->process($request, $handler);

        // The constructor order must be the execution order (remember-me first, 2FA last).
        self::assertSame(['rememberMe', 'sessionRevocation', 'passwordAge', 'twoFactorAuth'], $log);
    }

    private function createOpenSession(string $id): FakeSession
    {
        $session = new FakeSession();
        $session->setId($id);
        $session->open();

        return $session;
    }

    private function createPassThroughCookieMiddleware(): CookieMiddleware
    {
        return new CookieMiddleware(
            new NullLogger(),
            new CookieEncryptor('test-secret-key-0123456789abcdef'),
            new CookieSigner('test-secret-key-0123456789abcdef'),
        );
    }

    private function createPassThroughMiddleware(): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(
            static fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface => $handler->handle($request),
        );
        return $middleware;
    }

    private function createRealMiddleware(
        ?RememberMeMiddleware $rememberMe = null,
        ?PasswordAgeEnforceMiddleware $passwordAge = null,
        ?SessionRevocationEnforceMiddleware $sessionRevocation = null,
        ?TwoFactorAuthenticationEnforceMiddleware $twoFactorAuth = null,
        ?CookieMiddleware $cookieMiddleware = null,
        ?VoytiConfig $config = null,
        ?CurrentUser $currentUser = null,
        ?CurrentRoute $currentRoute = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?UrlGeneratorInterface $url = null,
        ?SessionInterface $session = null,
        ?TranslatorInterface $translator = null,
        ?ManagerInterface $authManager = null,
        ?IdentityRepositoryInterface $identityRepository = null,
    ): VoytiMiddleware {
        $config ??= VoytiConfigFactory::create();

        $currentUser ??= $this->createCurrentUser();
        $currentRoute ??= $this->createCurrentRoute();
        $responseFactory ??= $this->createMock(ResponseFactoryInterface::class);
        $url ??= $this->createMock(UrlGeneratorInterface::class);
        $session ??= new FakeSession();
        $translator ??= $this->createTranslator();
        $authManager ??= $this->createMock(ManagerInterface::class);
        $identityRepository ??= $this->createMock(IdentityRepositoryInterface::class);
        $cookieMiddleware ??= $this->createPassThroughCookieMiddleware();

        $rememberMe ??= new RememberMeMiddleware(
            $currentUser,
            new RememberMeCookieService(2592000, new SystemClock()),
            $identityRepository,
            $session,
            $cookieMiddleware,
        );

        $passwordAge ??= new PasswordAgeEnforceMiddleware(
            $currentUser,
            new ExpireService($config),
            $currentRoute,
            $translator,
            $responseFactory,
            $url,
        );

        $sessionRevocation ??= new SessionRevocationEnforceMiddleware(
            $currentUser,
            $currentRoute,
            $responseFactory,
            $session,
            $url,
        );

        $twoFactorAuth ??= new TwoFactorAuthenticationEnforceMiddleware(
            $currentUser,
            $config,
            $authManager,
            $currentRoute,
            $responseFactory,
            $translator,
            $url,
        );

        return new VoytiMiddleware($rememberMe, $sessionRevocation, $passwordAge, $twoFactorAuth);
    }

    private function createRecordingMiddleware(string $label, array &$log): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($label, &$log): ResponseInterface {
                $log[] = $label;
                return $handler->handle($request);
            },
        );
        return $middleware;
    }

    private function createRedirectMiddleware(ResponseInterface $response): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturn($response);
        return $middleware;
    }
}
