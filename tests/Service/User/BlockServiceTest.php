<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;

#[AllowMockObjectsWithoutExpectations]
final class BlockServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

    public function testRunWithBlockedUserUnblocks(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $service = new BlockService($eventDispatcher, new TerminateUserSessionsService(new EventCaptureDispatcher()));

        $user = $this->createUser();
        $user->setBlockedAt(time());
        $user->save();
        $this->createUserSession($user->getIdOrZero(), 'session-1');

        self::assertTrue($service->run($user));
        self::assertNull($user->getBlockedAt());
        self::assertCount(1, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame(UserEvent::UNBLOCK, $event->getType());

        // Unblocking must not terminate the user's active sessions.
        $session = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), 'session-1');
        self::assertNotNull($session);
        self::assertFalse($session->isRevoked());
    }

    public function testRunWithUnblockedUserBlocksAndTerminatesSessions(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $service = new BlockService($eventDispatcher, new TerminateUserSessionsService(new EventCaptureDispatcher()));

        $user = $this->createUser();
        $this->createUserSession($user->getIdOrZero(), 'session-1');

        self::assertTrue($service->run($user));
        self::assertNotNull($user->getBlockedAt());
        self::assertCount(1, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame(UserEvent::BLOCK, $event->getType());

        // Blocking terminates (revokes) the user's active sessions.
        $session = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), 'session-1');
        self::assertNotNull($session);
        self::assertTrue($session->isRevoked());
    }
}
