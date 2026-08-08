<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\tests\Support\CurrentRouteTrait;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class PasswordAgeEnforceMiddlewareTest extends TestCase
{
    use CurrentRouteTrait;
    use CurrentUserTrait;

    public static function exemptRouteProvider(): iterable
    {
        yield 'account settings' => ['voyti/user-account'];
        yield 'logout' => ['voyti/session-logout'];
    }

    public static function expiredPasswordProvider(): iterable
    {
        yield 'password expired' => [time() - 91 * 86400];
        yield 'password never changed' => [null];
    }

    #[DataProvider('exemptRouteProvider')]
    public function testProcessPassesThroughForExemptRoute(string $routeName): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);

        $user = new User();
        $user->setPasswordChangedAt(time() - 91 * 86400);

        $currentUser = $this->createCurrentUser($user);

        $currentRoute = $this->createCurrentRoute($routeName);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config, currentRoute: $currentRoute);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForGuestUser(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $currentUser = $this->createCurrentUser();

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughForNonUserIdentity(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $currentUser = $this->createCurrentUser($this->createMock(IdentityInterface::class));

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenMaxPasswordAgeIsZeroEvenIfPasswordVeryOld(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 0);

        $user = new User();
        $user->setPasswordChangedAt(time() - 9999 * 86400);

        $currentUser = $this->createCurrentUser($user);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessPassesThroughWhenPasswordNotExpired(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);

        $user = new User();
        $user->setPasswordChangedAt(time());

        $currentUser = $this->createCurrentUser($user);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->createMiddleware(currentUser: $currentUser, config: $config);
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[DataProvider('expiredPasswordProvider')]
    public function testProcessRedirectsWhenPasswordExpired(?int $passwordChangedAt): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);

        $user = new User();
        if ($passwordChangedAt !== null) {
            $user->setPasswordChangedAt($passwordChangedAt);
        }

        $currentUser = $this->createCurrentUser($user);

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects(self::once())->method('generate')->with('voyti/user-account')->willReturn('/voyti/user-account');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('withHeader')->with('Location', '/voyti/user-account')->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())->method('createResponse')->with(302)->willReturn($response);

        $middleware = $this->createMiddleware(
            currentUser: $currentUser,
            config: $config,
            responseFactory: $responseFactory,
            url: $url,
        );

        $middleware->process($request, $handler);
    }

    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?VoytiConfig $config = null,
        ?CurrentRoute $currentRoute = null,
        ?TranslatorInterface $translator = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?UrlGeneratorInterface $url = null,
    ): PasswordAgeEnforceMiddleware {
        $config ??= VoytiConfigFactory::create();

        return new PasswordAgeEnforceMiddleware(
            $currentUser ?? $this->createCurrentUser(),
            new ExpireService($config),
            $currentRoute ?? $this->createCurrentRoute(),
            $translator ?? $this->createTranslator(),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $url ?? $this->createMock(UrlGeneratorInterface::class),
        );
    }
}
