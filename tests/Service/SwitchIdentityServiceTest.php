<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\tests\Support\FakeSession;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SwitchIdentityServiceTest extends TestCase
{

    public function testRestoreSuccess(): void
    {
        $config = new ModuleConfig();
        $session = new FakeSession();
        $session->set('voyti_original_user', '42');

        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 42);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($user);

        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $service = $this->createService($config, $userRepository, $currentUser, $session);
        $result = $service->restore();

        self::assertTrue($result->isSuccess());
        self::assertFalse($session->has('voyti_original_user'));
    }

    public function testRestoreWithNullSessionKeyReturnsFailure(): void
    {
        $config = new ModuleConfig(switchIdentitySessionKey: null);
        $service = $this->createService($config);

        $result = $service->restore();

        self::assertTrue($result->isFailure());
        self::assertSame('No original identity to restore', $result->getMessage());
    }

    public function testRestoreWithNullSessionValueReturnsFailure(): void
    {
        $config = new ModuleConfig();
        $session = new FakeSession();
        $service = $this->createService($config, session: $session);

        $result = $service->restore();

        self::assertTrue($result->isFailure());
        self::assertSame('No original identity to restore', $result->getMessage());
    }

    public function testRestoreWithUserNotFoundReturnsFailure(): void
    {
        $config = new ModuleConfig();
        $session = new FakeSession();
        $session->set('voyti_original_user', '42');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn(null);

        $service = $this->createService($config, $userRepository, session: $session);
        $result = $service->restore();

        self::assertTrue($result->isFailure());
        self::assertSame('Original user not found', $result->getMessage());
    }

    public function testRunSuccessWithIdentity(): void
    {
        $config = new ModuleConfig();
        $targetUser = new User();

        $identity = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($identity, 1);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($targetUser);

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser->login($identity);

        $service = $this->createService($config, $userRepository, $currentUser, $session);
        $result = $service->run(42);

        self::assertTrue($result->isSuccess());
        self::assertSame('1', $session->get('voyti_original_user'));
    }

    public function testRunWithBlockedUserReturnsFailure(): void
    {
        $config = new ModuleConfig();
        $targetUser = new User();
        $ref = new \ReflectionProperty(User::class, 'blocked_at');
        $ref->setValue($targetUser, 12345);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($targetUser);

        $service = $this->createService($config, $userRepository);
        $result = $service->run(42);

        self::assertTrue($result->isFailure());
        self::assertSame('Cannot switch to a blocked user', $result->getMessage());
    }

    public function testRunWithNullIdentityDoesNotStoreSession(): void
    {
        $config = new ModuleConfig();
        $targetUser = new User();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($targetUser);

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $service = $this->createService($config, $userRepository, $currentUser, $session);
        $result = $service->run(42);

        self::assertTrue($result->isSuccess());
        self::assertFalse($session->has('voyti_original_user'));
    }

    public function testRunWithNullSwitchSessionKeyWhenIdentityPresentReturnsFailure(): void
    {
        $config = new ModuleConfig(switchIdentitySessionKey: null);
        $targetUser = new User();

        $identity = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($identity, 1);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($targetUser);

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser->login($identity);

        $service = $this->createService($config, $userRepository, $currentUser, $session);
        $result = $service->run(42);

        self::assertTrue($result->isFailure());
        self::assertSame('Switch identity session key is not configured', $result->getMessage());
    }

    public function testRunWithSwitchDisabledReturnsFailure(): void
    {
        $config = new ModuleConfig(enableSwitchIdentities: false);
        $service = $this->createService($config);

        $result = $service->run(42);

        self::assertTrue($result->isFailure());
        self::assertSame('Switch identities is disabled', $result->getMessage());
    }

    public function testRunWithUserNotFoundReturnsFailure(): void
    {
        $config = new ModuleConfig();
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn(null);

        $service = $this->createService($config, $userRepository);
        $result = $service->run(42);

        self::assertTrue($result->isFailure());
        self::assertSame('User not found', $result->getMessage());
    }
    private function createEventDispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        return $dispatcher;
    }

    private function createService(
        ModuleConfig $config,
        ?UserRepository $userRepository = null,
        ?CurrentUser $currentUser = null,
        ?FakeSession $session = null,
    ): SwitchIdentityService {
        $userRepository ??= $this->createMock(UserRepository::class);
        $currentUser ??= new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $session ??= new FakeSession();
        return new SwitchIdentityService($config, $userRepository, $currentUser, $session);
    }
}
