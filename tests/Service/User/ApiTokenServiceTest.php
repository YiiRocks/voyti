<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\User\ApiTokenService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

final class ApiTokenServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testGenerateReturnsRawTokenThatVerifiesAgainstStoredHash(): void
    {
        $service = new ApiTokenService();
        $user = $this->createUser();

        $rawToken = $service->generate($user);

        $stored = UserToken::findByCodeAndType($rawToken, UserToken::TYPE_API_ACCESS);

        self::assertSame(64, strlen($rawToken));
        self::assertNotNull($stored);
        self::assertSame($user->getId(), (string) $stored->getUserId());
        self::assertGreaterThan(0, $stored->getCreatedAt());
    }

    public function testRevokeAllDeletesOnlyApiAccessTokensForThatUser(): void
    {
        $service = new ApiTokenService();
        $user = $this->createUser();
        $otherUser = $this->createUser('otheruser', 'other@example.com');

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

        $remaining = UserToken::findByUserId((int) $user->getId());
        self::assertCount(1, $remaining);
        self::assertSame(UserToken::TYPE_CONFIRMATION, $remaining[0]->getType());

        $otherUserTokens = UserToken::findByUserId((int) $otherUser->getId());
        self::assertCount(1, $otherUserTokens);
    }
}
