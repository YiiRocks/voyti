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

    public function testRun(): void
    {
        // Unblock: removes blocked status without terminating sessions
        $eventDispatcher = new EventCaptureDispatcher();
        $service = new BlockService($eventDispatcher, new TerminateUserSessionsService(new EventCaptureDispatcher()));
        $user = $this->createUser(username: 'blocked_user', email: 'blocked@example.com');
        $user->setBlockedAt(time());
        $user->save();
        $this->createUserSession($user->getIdOrZero(), 'session-1');
        self::assertTrue($service->run($user));
        self::assertNull($user->getBlockedAt());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertSame(UserEvent::UNBLOCK, $event->getType());
        $session = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), 'session-1');
        self::assertFalse($session->isRevoked());

        // Block: sets blocked status and terminates sessions
        $eventDispatcher2 = new EventCaptureDispatcher();
        $service2 = new BlockService($eventDispatcher2, new TerminateUserSessionsService(new EventCaptureDispatcher()));
        $user2 = $this->createUser(username: 'active_user', email: 'active@example.com');
        $this->createUserSession($user2->getIdOrZero(), 'session-2');
        self::assertTrue($service2->run($user2));
        self::assertNotNull($user2->getBlockedAt());
        $event2 = $eventDispatcher2->getEvent(UserEvent::class);
        self::assertSame(UserEvent::BLOCK, $event2->getType());
        $session2 = UserSessions::findByUserIdAndSessionId($user2->getIdOrZero(), 'session-2');
        self::assertTrue($session2->isRevoked());
    }
}
