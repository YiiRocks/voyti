<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\User\ResendConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ResendConfirmationServiceTest extends TestCase
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

    public function testRunAlreadyConfirmedReturnsFalse(): void
    {
        $tokenFactory = new UserTokenFactory();
        $mailService = $this->createMock(MailService::class);
        $service = new ResendConfirmationService($tokenFactory, $mailService);

        $user = new User();
        $user->setUsername('confirmed');
        $user->setEmail('confirmed@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setConfirmedAt(time());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        self::assertFalse($service->run($user));
    }

    public function testRunSuccess(): void
    {
        $tokenFactory = new UserTokenFactory();
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendConfirmation')->willReturn(true);
        $service = new ResendConfirmationService($tokenFactory, $mailService);

        $user = new User();
        $user->setUsername('unconfirmed');
        $user->setEmail('unconfirmed@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        self::assertTrue($service->run($user));
    }
}
