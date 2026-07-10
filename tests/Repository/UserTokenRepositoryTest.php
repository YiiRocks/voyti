<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class UserTokenRepositoryTest extends TestCase
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

    public function testDeleteAllByUserIdRemovesOnlyThatUsersTokens(): void
    {
        $repo = new UserTokenRepository();

        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('user1token');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(2);
        $token2->setCode('user2token');
        $token2->setType(UserToken::TYPE_RECOVERY);
        $token2->setCreatedAt(time());
        $token2->save();

        $repo->deleteAllByUserId(1);

        self::assertCount(0, $repo->findByUserId(1));
        self::assertCount(1, $repo->findByUserId(2));
    }

    public function testFindByCodeAndTypeFiltersByCode(): void
    {
        $repo = new UserTokenRepository();

        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('codeB');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(1);
        $token2->setCode('codeA');
        $token2->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token2->setCreatedAt(time());
        $token2->save();

        $found = $repo->findByCodeAndType('codeA', UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());
    }

    public function testFindByUserIdAndCodeAndTypeReturnsMatch(): void
    {
        $repo = new UserTokenRepository();

        $token = new UserToken();
        $token->setUserId(1);
        $token->setCode('codeA');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $found = $repo->findByUserIdAndCodeAndType(1, 'codeA', UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());

        self::assertNull($repo->findByUserIdAndCodeAndType(1, 'codeA', UserToken::TYPE_RECOVERY));
    }

    public function testFindByUserIdAndCodeReturnsMatch(): void
    {
        $repo = new UserTokenRepository();

        $token = new UserToken();
        $token->setUserId(1);
        $token->setCode('codeA');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $found = $repo->findByUserIdAndCode(1, 'codeA');
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());

        self::assertNull($repo->findByUserIdAndCode(2, 'codeA'));
    }

    public function testFindByUserIdFiltersByUserId(): void
    {
        $repo = new UserTokenRepository();

        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('user1token');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(2);
        $token2->setCode('user2token');
        $token2->setType(UserToken::TYPE_RECOVERY);
        $token2->setCreatedAt(time());
        $token2->save();

        $tokens = $repo->findByUserId(1);
        self::assertCount(1, $tokens);
        self::assertSame('user1token', $tokens[0]->getCode());
    }

    public function testFindByUserIdRespectsAllResultsWhenMultipleMatch(): void
    {
        $repo = new UserTokenRepository();

        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('tokenA');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(1);
        $token2->setCode('tokenB');
        $token2->setType(UserToken::TYPE_RECOVERY);
        $token2->setCreatedAt(time());
        $token2->save();

        $tokens = $repo->findByUserId(1);
        self::assertCount(2, $tokens);
    }

    public function testFindByUserIdTypeAndCodeReturnsMatch(): void
    {
        $repo = new UserTokenRepository();

        $token = new UserToken();
        $token->setUserId(1);
        $token->setCode('codeA');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $found = $repo->findByUserIdTypeAndCode(1, UserToken::TYPE_CONFIRM_NEW_EMAIL, 'codeA');
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());
    }
}
