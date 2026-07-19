<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\VoytiMiddleware;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Http\Status;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class VoytiMiddlewareTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
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

        $middleware = new VoytiMiddleware($rememberMe, $first, $second, $third);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessShortCircuitsWhenFirstMiddlewareRedirects(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $redirectResponse = $this->createMock(ResponseInterface::class);

        $rememberMe = $this->createPassThroughMiddleware();
        $first = $this->createRedirectMiddleware($redirectResponse);
        $second = $this->createPassThroughMiddleware();
        $third = $this->createPassThroughMiddleware();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = new VoytiMiddleware($rememberMe, $first, $second, $third);
        $result = $middleware->process($request, $handler);

        self::assertSame($redirectResponse, $result);
    }

    public function testProcessShortCircuitsWhenRememberMeMiddlewareRedirects(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $redirectResponse = $this->createMock(ResponseInterface::class);

        $rememberMe = $this->createRedirectMiddleware($redirectResponse);
        $first = $this->createPassThroughMiddleware();
        $second = $this->createPassThroughMiddleware();
        $third = $this->createPassThroughMiddleware();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = new VoytiMiddleware($rememberMe, $first, $second, $third);
        $result = $middleware->process($request, $handler);

        self::assertSame($redirectResponse, $result);
    }

    public function testProcessShortCircuitsWhenSecondMiddlewareRedirects(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $redirectResponse = $this->createMock(ResponseInterface::class);

        $rememberMe = $this->createPassThroughMiddleware();
        $first = $this->createPassThroughMiddleware();
        $second = $this->createRedirectMiddleware($redirectResponse);
        $third = $this->createPassThroughMiddleware();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = new VoytiMiddleware($rememberMe, $first, $second, $third);
        $result = $middleware->process($request, $handler);

        self::assertSame($redirectResponse, $result);
    }

    public function testProcessShortCircuitsWhenThirdMiddlewareRedirects(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $redirectResponse = $this->createMock(ResponseInterface::class);

        $rememberMe = $this->createPassThroughMiddleware();
        $first = $this->createPassThroughMiddleware();
        $second = $this->createPassThroughMiddleware();
        $third = $this->createRedirectMiddleware($redirectResponse);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = new VoytiMiddleware($rememberMe, $first, $second, $third);
        $result = $middleware->process($request, $handler);

        self::assertSame($redirectResponse, $result);
    }

    public function testProcessWithRealMiddlewaresAllFeaturesDisabled(): void
    {
        $config = new ModuleConfig(
            enablePasswordExpiration: false,
            enableTwoFactorAuthentication: false,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createRealMiddleware(config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessWithRealMiddlewaresSessionRevocationShortCircuits(): void
    {
        $config = new ModuleConfig();

        $user = $this->createUser(username: 'voytiuser', email: 'voytiuser@example.com');

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

        $redirectResponse = $this->createMock(ResponseInterface::class);
        $redirectResponse->expects(self::once())->method('withHeader')->with('Location', '/voyti/session-login')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(Status::FOUND)->willReturn($redirectResponse);

        $session = $this->createOpenSession('revoked-session-id');

        $middleware = $this->createRealMiddleware(
            config: $config,
            currentUser: $currentUser,
            currentRoute: $currentRoute,
            responseFactory: $responseFactory,
            session: $session,
            url: $url,
        );

        $middleware->process($request, $handler);
    }

    private function createOpenSession(string $id): FakeSession
    {
        $session = new FakeSession();
        $session->setId($id);
        $session->open();

        return $session;
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
        ?ModuleConfig $config = null,
        ?CurrentUser $currentUser = null,
        ?CurrentRoute $currentRoute = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?UrlGeneratorInterface $url = null,
        ?SessionInterface $session = null,
        ?TranslatorInterface $translator = null,
        ?ManagerInterface $authManager = null,
        ?IdentityRepositoryInterface $identityRepository = null,
    ): VoytiMiddleware {
        $config ??= new ModuleConfig();

        $currentUser ??= $this->createMock(CurrentUser::class);
        $currentRoute ??= $this->createMock(CurrentRoute::class);
        $responseFactory ??= $this->createMock(ResponseFactoryInterface::class);
        $url ??= $this->createMock(UrlGeneratorInterface::class);
        $session ??= new FakeSession();
        $translator ??= $this->createMock(TranslatorInterface::class);
        $authManager ??= $this->createMock(ManagerInterface::class);
        $identityRepository ??= $this->createMock(IdentityRepositoryInterface::class);

        $rememberMe ??= new RememberMeMiddleware(
            $currentUser,
            new RememberMeCookieService(2592000, new SystemClock()),
            $identityRepository,
            $session,
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
            $responseFactory,
            $url,
        );

        return new VoytiMiddleware($rememberMe, $passwordAge, $sessionRevocation, $twoFactorAuth);
    }

    private function createRedirectMiddleware(ResponseInterface $response): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturn($response);
        return $middleware;
    }
}
