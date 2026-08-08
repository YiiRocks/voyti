<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class SwitchIdentityServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testRestore(): void
    {
        // Success: clears session and restores identity
        $config = VoytiConfigFactory::create();
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

        // Dispatches events
        $config = VoytiConfigFactory::create();
        $user2 = $this->createUser(username: 'restoredispatch', email: 'restoredispatch@example.com');
        $session2 = new FakeSession();
        $session2->set('voyti_original_admin_user', (string) $user2->getId());
        $currentUser2 = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $eventDispatcher2 = new EventCaptureDispatcher();
        $service2 = $this->createService($config, $currentUser2, $session2, $eventDispatcher2);
        $service2->restore();
        self::assertTrue($eventDispatcher2->hasEvent(UserEvent::class));
        self::assertTrue($eventDispatcher2->hasEvent(AfterLoginEvent::class));
        self::assertCount(2, $eventDispatcher2->getEvents());
        $userEvent = $eventDispatcher2->getEvent(UserEvent::class);
        self::assertNotNull($userEvent);
        self::assertSame(UserEvent::RESTORE_IDENTITY, $userEvent->getType());
        $identity = $currentUser2->getIdentity();
        self::assertInstanceOf(User::class, $identity);
        self::assertSame($user2->getId(), $identity->getId());
    }

    public function testRestoreErrors(): void
    {
        // No original identity in session
        $config = VoytiConfigFactory::create();
        $session = new FakeSession();
        $service = $this->createService($config, session: $session);
        $result = $service->restore();
        self::assertTrue($result->isFailure());
        self::assertSame('No original identity to restore', $result->getMessage());

        // Original user not found
        $config = VoytiConfigFactory::create();
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', '999999');
        $service = $this->createService($config, session: $session);
        $result = $service->restore();
        self::assertTrue($result->isFailure());
        self::assertSame('Original user not found', $result->getMessage());
    }

    public function testRun(): void
    {
        // Success: saves original identity and switches
        $config = VoytiConfigFactory::create();
        $targetUser = $this->createUser(username: 'runsuccess', email: 'runsuccess@example.com');
        $identity = new User();
        $ref = new ReflectionProperty(User::class, 'id');
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

        // Dispatches events
        $config = VoytiConfigFactory::create();
        $targetUser2 = $this->createUser(username: 'rundispatch', email: 'rundispatch@example.com');
        $identity2 = new User();
        $ref2 = new ReflectionProperty(User::class, 'id');
        $ref2->setValue($identity2, 888888);
        $session2 = new FakeSession();
        $currentUser2 = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createEventDispatcher(),
        );
        $currentUser2->login($identity2);
        $eventDispatcher2 = new EventCaptureDispatcher();
        $service2 = $this->createService($config, $currentUser2, $session2, $eventDispatcher2);
        $service2->run((int) $targetUser2->getId());
        self::assertTrue($eventDispatcher2->hasEvent(UserEvent::class));
        self::assertTrue($eventDispatcher2->hasEvent(AfterLoginEvent::class));
        self::assertCount(2, $eventDispatcher2->getEvents());
        $event = $eventDispatcher2->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame($targetUser2->getId(), $event->getUser()->getId());
        self::assertSame(UserEvent::SWITCH_IDENTITY, $event->getType());
        $identity = $currentUser2->getIdentity();
        self::assertInstanceOf(User::class, $identity);
        self::assertSame($targetUser2->getId(), $identity->getId());
    }

    public function testRunErrors(): void
    {
        // Switch disabled
        $config = VoytiConfigFactory::create(enableSwitchIdentities: false);
        $service = $this->createService($config);
        $result = $service->run(42);
        self::assertTrue($result->isFailure());
        self::assertSame('Switch identities is disabled', $result->getMessage());

        // User not found
        $config = VoytiConfigFactory::create();
        $service = $this->createService($config);
        $result = $service->run(999999);
        self::assertTrue($result->isFailure());
        self::assertSame('User not found', $result->getMessage());

        // Blocked user
        $config = VoytiConfigFactory::create();
        $blockedUser = $this->createUser(username: 'blockeduser', email: 'blockeduser@example.com');
        $blockedUser->setBlockedAt(12345);
        $blockedUser->save();
        $service = $this->createService($config);
        $result = $service->run((int) $blockedUser->getId());
        self::assertTrue($result->isFailure());
        self::assertSame('Cannot switch to a blocked user', $result->getMessage());

        // Self target
        $config = VoytiConfigFactory::create();
        $targetUser = $this->createUser(username: 'selftarget', email: 'selftarget@example.com');
        $identity = new User();
        $ref = new ReflectionProperty(User::class, 'id');
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

    private function createEventDispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        return $dispatcher;
    }

    private function createService(
        VoytiConfig $config,
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
