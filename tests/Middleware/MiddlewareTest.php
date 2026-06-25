<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

final class MiddlewareTest extends TestCase
{
    public function testAccessRuleRedirectsGuestsToConfiguredLoginPath(): void
    {
        $middleware = new AccessRuleMiddleware(
            $this->createCurrentUser(),
            new ModuleConfig(loginPath: '/custom/login'),
            $this->createAuthHelper($this->createManager([])),
            new Psr17Factory(),
        );

        $handler = new TrackingHandler();
        $response = $middleware->process($this->createRequest(), $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/custom/login', $response->getHeaderLine('Location'));
        self::assertFalse($handler->handled);
    }

    public function testAccessRuleReturnsForbiddenForNonAdminUsers(): void
    {
        $currentUser = $this->createCurrentUser($this->createIdentity('42'));

        $middleware = new AccessRuleMiddleware(
            $currentUser,
            new ModuleConfig(administratorPermissionName: 'admin'),
            $this->createAuthHelper($this->createManager([])),
            new Psr17Factory(),
        );

        $handler = new TrackingHandler();
        $response = $middleware->process($this->createRequest(), $handler);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($handler->handled);
    }

    public function testAccessRuleAllowsAdminUsersThrough(): void
    {
        $currentUser = $this->createCurrentUser($this->createIdentity('42'));
        $manager = $this->createManager(['admin' => new Permission('admin')]);

        $middleware = new AccessRuleMiddleware(
            $currentUser,
            new ModuleConfig(administratorPermissionName: 'admin'),
            $this->createAuthHelper($manager),
            new Psr17Factory(),
        );

        $handler = new TrackingHandler();
        $response = $middleware->process($this->createRequest(), $handler);

        self::assertTrue($handler->handled);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', (string) $response->getBody());
    }

    public function testPasswordAgeEnforcementRedirectsToConfiguredAccountSettingsPath(): void
    {
        $user = $this->createUserIdentity(
            id: '42',
            passwordChangedAt: time() - (91 * 86400),
        );

        $middleware = new PasswordAgeEnforceMiddleware(
            $this->createCurrentUser($user),
            new ModuleConfig(
                maxPasswordAge: 90,
                accountSettingsPath: '/custom/settings/account',
            ),
            $this->createStub(TranslatorInterface::class),
            new Psr17Factory(),
        );

        $handler = new TrackingHandler();
        $response = $middleware->process($this->createRequest(), $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/custom/settings/account', $response->getHeaderLine('Location'));
        self::assertFalse($handler->handled);
    }

    public function testPasswordAgeEnforcementAllowsRecentPasswordsThrough(): void
    {
        $user = $this->createUserIdentity(
            id: '42',
            passwordChangedAt: time() - (10 * 86400),
        );

        $middleware = new PasswordAgeEnforceMiddleware(
            $this->createCurrentUser($user),
            new ModuleConfig(maxPasswordAge: 90),
            $this->createStub(TranslatorInterface::class),
            new Psr17Factory(),
        );

        $handler = new TrackingHandler();
        $response = $middleware->process($this->createRequest(), $handler);

        self::assertTrue($handler->handled);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testTwoFactorEnforcementRedirectsToConfiguredAccountSettingsPath(): void
    {
        $user = $this->createUserIdentity(
            id: '42',
            authTfEnabled: false,
        );
        $manager = $this->createManager(['admin' => new Permission('admin')]);

        $middleware = new TwoFactorAuthenticationEnforceMiddleware(
            $this->createCurrentUser($user),
            new ModuleConfig(
                enableTwoFactorAuthentication: true,
                twoFactorAuthenticationForcedPermissions: ['admin'],
                accountSettingsPath: '/custom/settings/account',
            ),
            $manager,
            new Psr17Factory(),
        );

        $handler = new TrackingHandler();
        $response = $middleware->process($this->createRequest(), $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/custom/settings/account', $response->getHeaderLine('Location'));
        self::assertFalse($handler->handled);
    }

    public function testTwoFactorEnforcementAllowsEnabledUsersThrough(): void
    {
        $user = $this->createUserIdentity(
            id: '42',
            authTfEnabled: true,
        );
        $manager = $this->createManager(['admin' => new Permission('admin')]);

        $middleware = new TwoFactorAuthenticationEnforceMiddleware(
            $this->createCurrentUser($user),
            new ModuleConfig(
                enableTwoFactorAuthentication: true,
                twoFactorAuthenticationForcedPermissions: ['admin'],
            ),
            $manager,
            new Psr17Factory(),
        );

        $handler = new TrackingHandler();
        $response = $middleware->process($this->createRequest(), $handler);

        self::assertTrue($handler->handled);
        self::assertSame(200, $response->getStatusCode());
    }

    private function createCurrentUser(?IdentityInterface $identity = null): CurrentUser
    {
        $currentUser = new CurrentUser(
            $this->createStub(IdentityRepositoryInterface::class),
            new class implements EventDispatcherInterface {
                #[\Override]
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
        );

        if ($identity !== null) {
            $currentUser->overrideIdentity($identity);
        }

        return $currentUser;
    }

    private function createAuthHelper(ManagerInterface $manager): AuthHelper
    {
        return new AuthHelper(
            $manager,
            $this->createStub(ItemsStorageInterface::class),
            $this->createStub(AssignmentsStorageInterface::class),
            new ModuleConfig(administratorPermissionName: 'admin'),
        );
    }

    /**
     * @param array<string, Permission> $permissions
     */
    private function createManager(array $permissions): ManagerInterface
    {
        $manager = $this->createStub(ManagerInterface::class);
        $manager->method('getItemsByUserId')->willReturn($permissions);
        $manager->method('getPermissionsByUserId')->willReturn($permissions);
        return $manager;
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new class($id) implements IdentityInterface {
            public function __construct(private readonly string $id)
            {
            }

            #[\Override]
            public function getId(): ?string
            {
                return $this->id;
            }
        };
    }

    private function createRequest(): ServerRequestInterface
    {
        return new ServerRequest('GET', 'https://example.test/');
    }

    private function createUserIdentity(string $id, ?int $passwordChangedAt = null, bool $authTfEnabled = false): User
    {
        $user = new User();
        $this->setPrivateProperty($user, 'id', (int) $id);
        $this->setPrivateProperty($user, 'password_changed_at', $passwordChangedAt);
        $this->setPrivateProperty($user, 'auth_tf_enabled', $authTfEnabled);

        return $user;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}

final class TrackingHandler implements RequestHandlerInterface
{
    public bool $handled = false;

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handled = true;
        $factory = new Psr17Factory();
        return $factory->createResponse(200)->withBody($factory->createStream('ok'));
    }
}
