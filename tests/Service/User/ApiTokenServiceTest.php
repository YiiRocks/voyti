<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Helper\ApiTokenHasher;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\User\ApiTokenService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class ApiTokenServiceTest extends TestCase
{
    use DatabaseSetupTrait;

    private UserTokenRepository $userTokenRepository;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->userTokenRepository = new UserTokenRepository();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testGenerateDoesNotStoreRawTokenInPlaintext(): void
    {
        $service = new ApiTokenService($this->userTokenRepository);
        $user = $this->createSavedUser();

        $rawToken = $service->generate($user);

        $storedAsRaw = $this->userTokenRepository->findByCodeAndType($rawToken, UserToken::TYPE_API_ACCESS);

        self::assertNull($storedAsRaw);
    }

    public function testGenerateReturnsRawTokenThatVerifiesAgainstStoredHash(): void
    {
        $service = new ApiTokenService($this->userTokenRepository);
        $user = $this->createSavedUser();

        $rawToken = $service->generate($user);

        $stored = $this->userTokenRepository->findByCodeAndType(
            ApiTokenHasher::hash($rawToken),
            UserToken::TYPE_API_ACCESS,
        );

        self::assertSame(64, strlen($rawToken));
        self::assertNotNull($stored);
        self::assertSame($user->getId(), (string) $stored->getUserId());
        self::assertGreaterThan(0, $stored->getCreatedAt());
    }

    public function testRevokeAllDeletesOnlyApiAccessTokensForThatUser(): void
    {
        $service = new ApiTokenService($this->userTokenRepository);
        $user = $this->createSavedUser();
        $otherUser = $this->createSavedUser('otheruser', 'other@example.com');

        $service->generate($user);
        $service->generate($user);
        $service->generate($otherUser);

        $confirmationToken = new UserToken();
        $confirmationToken->setUserId((int) $user->getId());
        $confirmationToken->setType(UserToken::TYPE_CONFIRMATION);
        $confirmationToken->setCode('unrelated-code');
        $confirmationToken->setCreatedAt(time());
        $confirmationToken->save();

        $revokedCount = $service->revokeAll($user);

        self::assertSame(2, $revokedCount);

        $remaining = $this->userTokenRepository->findByUserId((int) $user->getId());
        self::assertCount(1, $remaining);
        self::assertSame(UserToken::TYPE_CONFIRMATION, $remaining[0]->getType());

        $otherUserTokens = $this->userTokenRepository->findByUserId((int) $otherUser->getId());
        self::assertCount(1, $otherUserTokens);
    }

    public function testRevokeAllReturnsZeroWhenUserHasNoApiTokens(): void
    {
        $service = new ApiTokenService($this->userTokenRepository);
        $user = $this->createSavedUser();

        self::assertSame(0, $service->revokeAll($user));
    }

    private function createSavedUser(string $username = 'testuser', string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }
}
