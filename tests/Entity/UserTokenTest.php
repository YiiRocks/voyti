<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserTokenTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user_token}} (
            user_id INTEGER NOT NULL,
            code VARCHAR(32) NOT NULL,
            type SMALLINT NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, code, type)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $db = $this->getDb();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
        ConnectionProvider::clear();
        parent::tearDown();
    }

    public function testCreateAndFind(): void
    {
        $userToken = new UserToken();
        $userToken->setUserId(1);
        $userToken->setCode('abc123def456');
        $userToken->setType(UserToken::TYPE_CONFIRMATION);
        $userToken->setCreatedAt(time());
        $userToken->save();

        $found = UserToken::query()->where(['user_id' => 1, 'code' => 'abc123def456', 'type' => UserToken::TYPE_CONFIRMATION])->one();
        $this->assertInstanceOf(UserToken::class, $found);
        $this->assertSame(1, $found->getUserId());
        $this->assertSame('abc123def456', $found->getCode());
        $this->assertSame(UserToken::TYPE_CONFIRMATION, $found->getType());
    }

    public function testCreatedAt(): void
    {
        $now = time();
        $userToken = new UserToken();
        $userToken->setUserId(5);
        $userToken->setCode('time_test');
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCreatedAt($now);
        $userToken->save();

        $found = UserToken::query()->where(['code' => 'time_test'])->one();
        $this->assertInstanceOf(UserToken::class, $found);
        $this->assertEquals($now, $found->getCreatedAt());
    }

    public function testDeleteToken(): void
    {
        $userToken = new UserToken();
        $userToken->setUserId(4);
        $userToken->setCode('delete_me');
        $userToken->setType(UserToken::TYPE_CONFIRMATION);
        $userToken->setCreatedAt(time());
        $userToken->save();

        $userToken->delete();

        $found = UserToken::query()->where(['user_id' => 4, 'code' => 'delete_me', 'type' => UserToken::TYPE_CONFIRMATION])->one();
        $this->assertNull($found);
    }

    public function testNotFoundWithWrongPk(): void
    {
        $userToken = new UserToken();
        $userToken->setUserId(3);
        $userToken->setCode('unique_code');
        $userToken->setType(UserToken::TYPE_CONFIRMATION);
        $userToken->setCreatedAt(time());
        $userToken->save();

        $notFound = UserToken::query()->where(['user_id' => 3, 'code' => 'wrong_code', 'type' => UserToken::TYPE_CONFIRMATION])->one();
        $this->assertNull($notFound);

        $notFound2 = UserToken::query()->where(['user_id' => 99, 'code' => 'unique_code', 'type' => UserToken::TYPE_CONFIRMATION])->one();
        $this->assertNull($notFound2);
    }

    public function testTokenTypes(): void
    {
        $userToken = new UserToken();
        $userToken->setUserId(2);
        $userToken->setCode('recovery789');
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCreatedAt(time());
        $userToken->save();

        $found = UserToken::query()->where(['code' => 'recovery789'])->one();
        $this->assertInstanceOf(UserToken::class, $found);
        $this->assertSame(UserToken::TYPE_RECOVERY, $found->getType());

        $token2 = new UserToken();
        $token2->setUserId(2);
        $token2->setCode('confirm_new');
        $token2->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token2->setCreatedAt(time());
        $token2->save();

        $token3 = new UserToken();
        $token3->setUserId(2);
        $token3->setCode('confirm_old');
        $token3->setType(UserToken::TYPE_CONFIRM_OLD_EMAIL);
        $token3->setCreatedAt(time());
        $token3->save();

        $this->assertSame(3, UserToken::query()->where(['user_id' => 2])->count());
    }
}
