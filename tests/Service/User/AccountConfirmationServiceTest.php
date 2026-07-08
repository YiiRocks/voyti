<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\User\AccountConfirmationService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AccountConfirmationServiceTest extends TestCase
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

    public function testRunConfirmationServiceFailsReturnsFalse(): void
    {
        $userTokenRepository = new UserTokenRepository();

        $user = $this->createUnconfirmedUser();
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode('validcode');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $service = new AccountConfirmationService($userTokenRepository);
        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->method('run')->willReturn(false);

        self::assertFalse($service->run('validcode', $user, $confirmationService));
    }

    public function testRunSuccess(): void
    {
        $userTokenRepository = new UserTokenRepository();

        $user = $this->createUnconfirmedUser();
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode('successcode');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $service = new AccountConfirmationService($userTokenRepository);
        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->method('run')->willReturn(true);

        self::assertTrue($service->run('successcode', $user, $confirmationService));

        $foundToken = $userTokenRepository->findByUserIdAndCode((int) $user->getId(), 'successcode');
        self::assertNull($foundToken);
    }

    public function testRunTokenExpiredReturnsFalse(): void
    {
        $userTokenRepository = new UserTokenRepository();

        $user = $this->createUnconfirmedUser();
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode('expiredcode');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time() - 200000);
        $token->save();

        $service = new AccountConfirmationService($userTokenRepository);
        $confirmationService = $this->createMock(ConfirmationService::class);

        self::assertFalse($service->run('expiredcode', $user, $confirmationService));
    }

    public function testRunTokenNotFoundReturnsFalse(): void
    {
        $userTokenRepository = new UserTokenRepository();
        $service = new AccountConfirmationService($userTokenRepository);
        $user = $this->createUnconfirmedUser();
        $confirmationService = $this->createMock(ConfirmationService::class);

        self::assertFalse($service->run('nonexistent', $user, $confirmationService));
    }

    public function testRunUserAlreadyConfirmedReturnsFalse(): void
    {
        $userTokenRepository = new UserTokenRepository();
        $service = new AccountConfirmationService($userTokenRepository);
        $user = $this->createConfirmedUser();
        $confirmationService = $this->createMock(ConfirmationService::class);

        self::assertFalse($service->run('code', $user, $confirmationService));
    }

    private function createConfirmedUser(): User
    {
        $user = new User();
        $user->setUsername('confirmed');
        $user->setEmail('confirmed@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setConfirmedAt(time());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }

    private function createUnconfirmedUser(): User
    {
        $user = new User();
        $user->setUsername('unconfirmed');
        $user->setEmail('unconfirmed@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }
}
