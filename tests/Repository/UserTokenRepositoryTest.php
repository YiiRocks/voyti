<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserTokenRepositoryTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $this->getDb()->createCommand('CREATE TABLE {{%user_token}} (
            user_id INTEGER NOT NULL,
            code VARCHAR(64) NOT NULL,
            type INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, code, type)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testDeleteAllByUserIdOnlyDeletesTokensForGivenUser(): void
    {
        $this->insertToken(1, 'code-1', UserToken::TYPE_CONFIRMATION);
        $this->insertToken(2, 'code-2', UserToken::TYPE_CONFIRMATION);

        $repository = new UserTokenRepository();
        $repository->deleteAllByUserId(1);

        self::assertSame([], $repository->findByUserId(1));
        self::assertNotNull($repository->findByUserIdAndCode(2, 'code-2'));
    }

    public function testFindByUserIdAndCodeAndTypeOnlyReturnsMatchingUser(): void
    {
        $this->insertToken(1, 'shared-code', UserToken::TYPE_RECOVERY);
        $this->insertToken(2, 'shared-code', UserToken::TYPE_RECOVERY);

        $repository = new UserTokenRepository();
        $result = $repository->findByUserIdAndCodeAndType(1, 'shared-code', UserToken::TYPE_RECOVERY);

        self::assertSame(1, $result->getUserId());

        self::assertNull($repository->findByUserIdAndCodeAndType(999, 'shared-code', UserToken::TYPE_RECOVERY));
    }

    public function testFindByUserIdAndCodeOnlyReturnsMatchingUser(): void
    {
        $this->insertToken(1, 'shared-code', UserToken::TYPE_CONFIRMATION);
        $this->insertToken(2, 'shared-code', UserToken::TYPE_CONFIRMATION);

        $repository = new UserTokenRepository();
        $result = $repository->findByUserIdAndCode(1, 'shared-code');

        self::assertSame(1, $result->getUserId());

        self::assertNull($repository->findByUserIdAndCode(999, 'shared-code'));
    }

    public function testFindByUserIdOnlyReturnsTokensForGivenUser(): void
    {
        $this->insertToken(1, 'code-1', UserToken::TYPE_CONFIRMATION);
        $this->insertToken(2, 'code-2', UserToken::TYPE_CONFIRMATION);
        $this->insertToken(1, 'code-3', UserToken::TYPE_RECOVERY);

        $repository = new UserTokenRepository();
        $result = $repository->findByUserId(1);

        $codes = array_map(static fn (UserToken $token): string => $token->getCode(), $result);
        sort($codes);
        self::assertSame(['code-1', 'code-3'], $codes);
        foreach ($result as $token) {
            self::assertSame(1, $token->getUserId());
        }
    }

    private function insertToken(int $userId, string $code, int $type): void
    {
        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode($code);
        $token->setType($type);
        $token->setCreatedAt(1_700_000_000);
        $token->save();
    }
}
