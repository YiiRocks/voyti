<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
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
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $terminateService = $this->createMock(TerminateUserSessionsService::class);
        $terminateService->expects($this->never())->method('run');

        $service = new BlockService($eventDispatcher, $terminateService);

        $user = $this->createSavedUser();
        $user->setBlockedAt(time());
        $user->save();

        self::assertTrue($service->run($user));
        self::assertNull($user->getBlockedAt());
    }

    public function testRunWithUnblockedUserBlocksAndTerminatesSessions(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $terminateService = $this->createMock(TerminateUserSessionsService::class);
        $terminateService->expects($this->once())->method('run');

        $service = new BlockService($eventDispatcher, $terminateService);

        $user = $this->createSavedUser();

        self::assertTrue($service->run($user));
        self::assertNotNull($user->getBlockedAt());
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
