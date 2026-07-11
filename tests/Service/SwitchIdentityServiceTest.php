<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeSession;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SwitchIdentityServiceTest extends TestCase
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

    public function testGetOriginalUserReturnsNullWhenSessionHasNoKey(): void
    {
        $config = new ModuleConfig();
        $session = new FakeSession();

        $service = $this->createService($config, session: $session);

        self::assertNull($service->getOriginalUser());
    }

    public function testGetOriginalUserReturnsNullWhenSessionKeyIsNull(): void
    {
        $config = new ModuleConfig(switchIdentitySessionKey: null);
        $service = $this->createService($config);

        self::assertNull($service->getOriginalUser());
    }

    public function testGetOriginalUserReturnsUserWhenSessionHasKey(): void
    {
        $config = new ModuleConfig();
        $user = $this->createUser('original');
        $session = new FakeSession();
        $session->set('voyti_original_user', (string) $user->getId());

        $service = $this->createService($config, session: $session);

        $found = $service->getOriginalUser();
        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testIsSwitchedReturnsFalseWhenSessionHasNoKey(): void
    {
        $config = new ModuleConfig();
        $session = new FakeSession();

        $service = $this->createService($config, session: $session);

        self::assertFalse($service->isSwitched());
    }

    public function testIsSwitchedReturnsFalseWhenSessionKeyIsNull(): void
    {
        $config = new ModuleConfig(switchIdentitySessionKey: null);
        $service = $this->createService($config);

        self::assertFalse($service->isSwitched());
    }

    public function testIsSwitchedReturnsTrueWhenSessionHasKey(): void
    {
        $config = new ModuleConfig();
        $session = new FakeSession();
        $session->set('voyti_original_user', '42');

        $service = $this->createService($config, session: $session);

        self::assertTrue($service->isSwitched());
    }

    public function testRestoreDispatchesEvents(): void
    {
        $config = new ModuleConfig();
        $user = $this->createUser('restoredispatch');
        $session = new FakeSession();
        $session->set('voyti_original_user', (string) $user->getId());

        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $eventDispatcher = new \YiiRocks\Voyti\tests\Support\EventCaptureDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $service->restore();

        self::assertTrue($eventDispatcher->hasEvent(UserEvent::class));
        self::assertCount(2, $eventDispatcher->getEvents());
        $identity = $currentUser->getIdentity();
        self::assertInstanceOf(User::class, $identity);
        self::assertSame($user->getId(), $identity->getId());
    }

    public function testRestoreSuccess(): void
    {
        $config = new ModuleConfig();
        $user = $this->createUser('restoresuccess');
        $session = new FakeSession();
        $session->set('voyti_original_user', (string) $user->getId());

        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $eventDispatcher = $this->createEventDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
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
        $session->set('voyti_original_user', '999999');

        $service = $this->createService($config, session: $session);
        $result = $service->restore();

        self::assertTrue($result->isFailure());
        self::assertSame('Original user not found', $result->getMessage());
    }

    public function testRunDispatchesEvents(): void
    {
        $config = new ModuleConfig();
        $targetUser = $this->createUser('rundispatch');

        $identity = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($identity, 999999);

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser->login($identity);

        $eventDispatcher = new \YiiRocks\Voyti\tests\Support\EventCaptureDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $service->run((int) $targetUser->getId());

        self::assertTrue($eventDispatcher->hasEvent(UserEvent::class));
        self::assertCount(2, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame($targetUser->getId(), $event->getUser()->getId());
        $identity = $currentUser->getIdentity();
        self::assertInstanceOf(User::class, $identity);
        self::assertSame($targetUser->getId(), $identity->getId());
    }

    public function testRunSuccessWithIdentity(): void
    {
        $config = new ModuleConfig();
        $targetUser = $this->createUser('runsuccess');

        $identity = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($identity, 999999);

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser->login($identity);

        $eventDispatcher = $this->createEventDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $result = $service->run((int) $targetUser->getId());

        self::assertTrue($result->isSuccess());
        self::assertSame('999999', $session->get('voyti_original_user'));
    }

    public function testRunWithBlockedUserReturnsFailure(): void
    {
        $config = new ModuleConfig();
        $targetUser = $this->createUser('blockeduser');
        $targetUser->setBlockedAt(12345);
        $targetUser->save();

        $service = $this->createService($config);
        $result = $service->run((int) $targetUser->getId());

        self::assertTrue($result->isFailure());
        self::assertSame('Cannot switch to a blocked user', $result->getMessage());
    }

    public function testRunWithNullIdentityDoesNotStoreSession(): void
    {
        $config = new ModuleConfig();
        $targetUser = $this->createUser('nullidentity');

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $eventDispatcher = $this->createEventDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $result = $service->run((int) $targetUser->getId());

        self::assertTrue($result->isSuccess());
        self::assertFalse($session->has('voyti_original_user'));
    }

    public function testRunWithNullSwitchSessionKeyWhenIdentityPresentReturnsFailure(): void
    {
        $config = new ModuleConfig(switchIdentitySessionKey: null);
        $targetUser = $this->createUser('nullsessionkey');

        $identity = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($identity, 999999);

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser->login($identity);

        $service = $this->createService($config, $currentUser, $session);
        $result = $service->run((int) $targetUser->getId());

        self::assertTrue($result->isFailure());
        self::assertSame('Switch identity session key is not configured', $result->getMessage());
    }

    public function testRunWithSelfTargetReturnsFailure(): void
    {
        $config = new ModuleConfig();
        $targetUser = $this->createUser('selftarget');

        $identity = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($identity, (int) $targetUser->getId());

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser->login($identity);

        $service = $this->createService($config, $currentUser, $session);
        $result = $service->run((int) $targetUser->getId());

        self::assertTrue($result->isFailure());
        self::assertSame('Cannot switch to yourself', $result->getMessage());
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
        $service = $this->createService($config);
        $result = $service->run(999999);

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
        ?CurrentUser $currentUser = null,
        ?FakeSession $session = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): SwitchIdentityService {
        $currentUser ??= new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $session ??= new FakeSession();
        $eventDispatcher ??= $this->createEventDispatcher();
        return new SwitchIdentityService($config, $currentUser, $session, $eventDispatcher);
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
