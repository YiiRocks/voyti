<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;

#[AllowMockObjectsWithoutExpectations]
final class BlockServiceTest extends TestCase
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

    public function testRunWithBlockedUserUnblocks(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $terminateService = $this->createMock(TerminateUserSessionsService::class);
        $terminateService->expects($this->never())->method('run');

        $service = new BlockService($eventDispatcher, $terminateService);

        $user = $this->createSavedUser();
        $user->setBlockedAt(time());
        $user->save();

        self::assertTrue($service->run($user));
        self::assertNull($user->getBlockedAt());
        self::assertCount(1, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame(UserEvent::UNBLOCK, $event->getType());
    }

    public function testRunWithUnblockedUserBlocksAndTerminatesSessions(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $terminateService = $this->createMock(TerminateUserSessionsService::class);
        $terminateService->expects($this->once())->method('run');

        $service = new BlockService($eventDispatcher, $terminateService);

        $user = $this->createSavedUser();

        self::assertTrue($service->run($user));
        self::assertNotNull($user->getBlockedAt());
        self::assertCount(1, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame(UserEvent::BLOCK, $event->getType());
    }

    private function createSavedUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }
}
