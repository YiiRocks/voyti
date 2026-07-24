<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class SwitchIdentityServiceTest extends TestCase
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

    public function testGetOriginalUserReturnsUserWhenSessionHasKey(): void
    {
        $config = ModuleConfigFactory::create();
        $user = $this->createUser(username: 'original', email: 'original@example.com');
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', (string) $user->getId());

        $service = $this->createService($config, session: $session);

        $found = $service->getOriginalUser();
        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testIsSwitchedReturnsTrueWhenSessionHasKey(): void
    {
        $config = ModuleConfigFactory::create();
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', '42');

        $service = $this->createService($config, session: $session);

        self::assertTrue($service->isSwitched());
    }

    public function testRestoreDispatchesEvents(): void
    {
        $config = ModuleConfigFactory::create();
        $user = $this->createUser(username: 'restoredispatch', email: 'restoredispatch@example.com');
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', (string) $user->getId());

        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $eventDispatcher = new EventCaptureDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $service->restore();

        self::assertTrue($eventDispatcher->hasEvent(UserEvent::class));
        self::assertTrue($eventDispatcher->hasEvent(AfterLoginEvent::class));
        self::assertCount(2, $eventDispatcher->getEvents());
        $userEvent = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($userEvent);
        self::assertSame(UserEvent::RESTORE_IDENTITY, $userEvent->getType());
        $identity = $currentUser->getIdentity();
        self::assertInstanceOf(User::class, $identity);
        self::assertSame($user->getId(), $identity->getId());
    }

    public function testRestoreSuccess(): void
    {
        $config = ModuleConfigFactory::create();
        $user = $this->createUser(username: 'restoresuccess', email: 'restoresuccess@example.com');
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', (string) $user->getId());

        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $eventDispatcher = $this->createEventDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $result = $service->restore();

        self::assertTrue($result->isSuccess());
        self::assertFalse($session->has('voyti_original_admin_user'));
    }

    public function testRestoreWithNullSessionValueReturnsFailure(): void
    {
        $config = ModuleConfigFactory::create();
        $session = new FakeSession();
        $service = $this->createService($config, session: $session);

        $result = $service->restore();

        self::assertTrue($result->isFailure());
        self::assertSame('No original identity to restore', $result->getMessage());
    }

    public function testRestoreWithUserNotFoundReturnsFailure(): void
    {
        $config = ModuleConfigFactory::create();
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', '999999');

        $service = $this->createService($config, session: $session);
        $result = $service->restore();

        self::assertTrue($result->isFailure());
        self::assertSame('Original user not found', $result->getMessage());
    }

    public function testRunDispatchesEvents(): void
    {
        $config = ModuleConfigFactory::create();
        $targetUser = $this->createUser(username: 'rundispatch', email: 'rundispatch@example.com');

        $identity = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($identity, 999999);

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser->login($identity);

        $eventDispatcher = new EventCaptureDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $service->run((int) $targetUser->getId());

        self::assertTrue($eventDispatcher->hasEvent(UserEvent::class));
        self::assertTrue($eventDispatcher->hasEvent(AfterLoginEvent::class));
        self::assertCount(2, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame($targetUser->getId(), $event->getUser()->getId());
        self::assertSame(UserEvent::SWITCH_IDENTITY, $event->getType());
        $identity = $currentUser->getIdentity();
        self::assertInstanceOf(User::class, $identity);
        self::assertSame($targetUser->getId(), $identity->getId());
    }

    public function testRunSuccessWithIdentity(): void
    {
        $config = ModuleConfigFactory::create();
        $targetUser = $this->createUser(username: 'runsuccess', email: 'runsuccess@example.com');

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
        self::assertSame('999999', $session->get('voyti_original_admin_user'));
    }

    public function testRunWithBlockedUserReturnsFailure(): void
    {
        $config = ModuleConfigFactory::create();
        $targetUser = $this->createUser(username: 'blockeduser', email: 'blockeduser@example.com');
        $targetUser->setBlockedAt(12345);
        $targetUser->save();

        $service = $this->createService($config);
        $result = $service->run((int) $targetUser->getId());

        self::assertTrue($result->isFailure());
        self::assertSame('Cannot switch to a blocked user', $result->getMessage());
    }

    public function testRunWithNullIdentityDoesNotStoreSession(): void
    {
        $config = ModuleConfigFactory::create();
        $targetUser = $this->createUser(username: 'nullidentity', email: 'nullidentity@example.com');

        $session = new FakeSession();
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );

        $eventDispatcher = $this->createEventDispatcher();
        $service = $this->createService($config, $currentUser, $session, $eventDispatcher);
        $result = $service->run((int) $targetUser->getId());

        self::assertTrue($result->isSuccess());
        self::assertFalse($session->has('voyti_original_admin_user'));
    }

    public function testRunWithSelfTargetReturnsFailure(): void
    {
        $config = ModuleConfigFactory::create();
        $targetUser = $this->createUser(username: 'selftarget', email: 'selftarget@example.com');

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
        $config = ModuleConfigFactory::create(enableSwitchIdentities: false);
        $service = $this->createService($config);

        $result = $service->run(42);

        self::assertTrue($result->isFailure());
        self::assertSame('Switch identities is disabled', $result->getMessage());
    }

    public function testRunWithUserNotFoundReturnsFailure(): void
    {
        $config = ModuleConfigFactory::create();
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

}
