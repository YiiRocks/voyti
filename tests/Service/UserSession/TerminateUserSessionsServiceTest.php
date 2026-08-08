<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\UserSession;

use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;

final class TerminateUserSessionsServiceTest extends DatabaseTestCase
{
    public function testRunCanBeCalledWithZero(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();

        $service = new TerminateUserSessionsService($eventDispatcher);
        $service->run(0);

        $event = $eventDispatcher->getEvent(SessionEvent::class);
        self::assertInstanceOf(SessionEvent::class, $event);
        self::assertSame(['type' => SessionEvent::SESSION_TERMINATED], $event->getData());
    }

    public function testRunDoesNotOverwriteAlreadyRevokedTimestamp(): void
    {
        $session = new UserSessions();
        $session->setUserId(42);
        $session->setSessionId('already-revoked');
        $session->setIp('127.0.0.1');
        $session->setRevokedAt(1000);
        $session->save();

        $eventDispatcher = new EventCaptureDispatcher();
        $service = new TerminateUserSessionsService($eventDispatcher);
        $service->run(42);

        $refreshed = UserSessions::query()->where(['user_id' => 42, 'session_id' => 'already-revoked'])->one();
        self::assertNotNull($refreshed);
        self::assertSame(1000, $refreshed->getRevokedAt());
    }

    public function testRunDoesNotRevokeUnrelatedUserId(): void
    {
        $session = new UserSessions();
        $session->setUserId(99);
        $session->setSessionId('keep');
        $session->setIp('127.0.0.1');
        $session->save();

        $eventDispatcher = new EventCaptureDispatcher();
        $service = new TerminateUserSessionsService($eventDispatcher);
        $service->run(123);

        $kept = UserSessions::query()->where(['user_id' => 99, 'session_id' => 'keep'])->one();
        self::assertNotNull($kept);
        self::assertFalse($kept->isRevoked());
    }
}
