<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use Yiisoft\Db\Connection\ConnectionProvider;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\Entity\Token;

final class TokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%token}} (
            userId INTEGER NOT NULL,
            code VARCHAR(32) NOT NULL,
            type SMALLINT NOT NULL,
            createdAt INTEGER NOT NULL,
            PRIMARY KEY (userId, code, type)
        )')->execute();
    }

    protected function tearDown(): void
    {
        $db = $this->getDb();
        $db->createCommand('DROP TABLE IF EXISTS {{%token}}')->execute();
        ConnectionProvider::clear();
        parent::tearDown();
    }

    public function testCreateAndFind(): void
    {
        $token = new Token();
        $token->setUserId(1);
        $token->setCode('abc123def456');
        $token->setType(Token::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $found = Token::query()->where(['userId' => 1, 'code' => 'abc123def456', 'type' => Token::TYPE_CONFIRMATION])->one();
        $this->assertNotNull($found);
        $this->assertSame(1, $found->getUserId());
        $this->assertSame('abc123def456', $found->getCode());
        $this->assertSame(Token::TYPE_CONFIRMATION, $found->getType());
    }

    public function testTokenTypes(): void
    {
        $token = new Token();
        $token->setUserId(2);
        $token->setCode('recovery789');
        $token->setType(Token::TYPE_RECOVERY);
        $token->setCreatedAt(time());
        $token->save();

        $found = Token::query()->where(['code' => 'recovery789'])->one();
        $this->assertNotNull($found);
        $this->assertSame(Token::TYPE_RECOVERY, $found->getType());

        $token2 = new Token();
        $token2->setUserId(2);
        $token2->setCode('confirm_new');
        $token2->setType(Token::TYPE_CONFIRM_NEW_EMAIL);
        $token2->setCreatedAt(time());
        $token2->save();

        $token3 = new Token();
        $token3->setUserId(2);
        $token3->setCode('confirm_old');
        $token3->setType(Token::TYPE_CONFIRM_OLD_EMAIL);
        $token3->setCreatedAt(time());
        $token3->save();

        $this->assertSame(3, Token::query()->where(['userId' => 2])->count());
    }

    public function testNotFoundWithWrongPk(): void
    {
        $token = new Token();
        $token->setUserId(3);
        $token->setCode('unique_code');
        $token->setType(Token::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $notFound = Token::query()->where(['userId' => 3, 'code' => 'wrong_code', 'type' => Token::TYPE_CONFIRMATION])->one();
        $this->assertNull($notFound);

        $notFound2 = Token::query()->where(['userId' => 99, 'code' => 'unique_code', 'type' => Token::TYPE_CONFIRMATION])->one();
        $this->assertNull($notFound2);
    }

    public function testDeleteToken(): void
    {
        $token = new Token();
        $token->setUserId(4);
        $token->setCode('delete_me');
        $token->setType(Token::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $token->delete();

        $found = Token::query()->where(['userId' => 4, 'code' => 'delete_me', 'type' => Token::TYPE_CONFIRMATION])->one();
        $this->assertNull($found);
    }

    public function testCreatedAt(): void
    {
        $now = time();
        $token = new Token();
        $token->setUserId(5);
        $token->setCode('time_test');
        $token->setType(Token::TYPE_RECOVERY);
        $token->setCreatedAt($now);
        $token->save();

        $found = Token::query()->where(['code' => 'time_test'])->one();
        $this->assertNotNull($found);
        $this->assertEquals($now, $found->getCreatedAt());
    }
}
