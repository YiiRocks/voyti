<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\UserSession;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\UserSession;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;

final class TerminateUserSessionsServiceTest extends TestCase
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

    public function testRunCanBeCalledWithZero(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();

        $service = new TerminateUserSessionsService($eventDispatcher);
        $service->run(0);

        $event = $eventDispatcher->getEvent(SessionEvent::class);
        self::assertInstanceOf(SessionEvent::class, $event);
        self::assertSame(['type' => SessionEvent::SESSION_TERMINATED], $event->getData());
    }

    public function testRunDeletesNothingForUnrelatedUserId(): void
    {
        $session = new UserSession();
        $session->setUserId(99);
        $session->setSessionId('keep');
        $session->setIp('127.0.0.1');
        $session->save();

        $eventDispatcher = new EventCaptureDispatcher();
        $service = new TerminateUserSessionsService($eventDispatcher);
        $service->run(123);

        self::assertCount(1, UserSession::query()->where(['user_id' => 99, 'session_id' => 'keep'])->all());
    }

    public function testRunDeletesOnlyMatchingUserSessions(): void
    {
        $other = new UserSession();
        $other->setUserId(7);
        $other->setSessionId('keep');
        $other->setIp('127.0.0.1');
        $other->save();

        $mine = new UserSession();
        $mine->setUserId(42);
        $mine->setSessionId('remove');
        $mine->setIp('127.0.0.1');
        $mine->save();

        $eventDispatcher = new EventCaptureDispatcher();
        $service = new TerminateUserSessionsService($eventDispatcher);
        $service->run(42);

        self::assertCount(0, UserSession::query()->where(['user_id' => 42, 'session_id' => 'remove'])->all());
        self::assertCount(1, UserSession::query()->where(['user_id' => 7, 'session_id' => 'keep'])->all());
    }

    public function testRunDispatchesEvent(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();

        $service = new TerminateUserSessionsService($eventDispatcher);
        $service->run(42);

        $event = $eventDispatcher->getEvent(SessionEvent::class);
        self::assertInstanceOf(SessionEvent::class, $event);
        self::assertSame(42, $event->getUserId());
        self::assertSame('', $event->getSessionId());
        self::assertSame(['type' => SessionEvent::SESSION_TERMINATED], $event->getData());
    }
}
