<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Factory;

use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserTokenFactoryTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $this->getDb()->createCommand('CREATE TABLE {{%user_token}} (
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
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testGeneratesDifferentCodesAcrossCalls(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());

        $codeA = $factory->makeConfirmationToken(21)->getCode();
        $codeB = $factory->makeRecoveryToken(22)->getCode();

        self::assertNotSame($codeA, $codeB);
    }

    public function testMakeConfirmationTokenGeneratesCodeOfExactly32Characters(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());

        $token = $factory->makeConfirmationToken(7);

        self::assertSame(32, strlen($token->getCode()));
    }

    public function testMakeConfirmationTokenPersistsTokenWithCode(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());

        $before = time();
        $token = $factory->makeConfirmationToken(11);
        $after = time();

        self::assertSame(11, $token->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRMATION, $token->getType());
        self::assertNotSame('', $token->getCode());
        self::assertGreaterThanOrEqual($before, $token->getCreatedAt());
        self::assertLessThanOrEqual($after, $token->getCreatedAt());

        $persisted = UserToken::query()->findByPk(['user_id' => 11, 'code' => $token->getCode(), 'type' => UserToken::TYPE_CONFIRMATION]);
        self::assertSame($token->getCode(), $persisted->getCode());
    }

    public function testMakeConfirmNewMailTokenUsesConfirmNewEmailType(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());

        $token = $factory->makeConfirmNewMailToken(3);

        self::assertSame(UserToken::TYPE_CONFIRM_NEW_EMAIL, $token->getType());
    }

    public function testMakeConfirmOldMailTokenUsesConfirmOldEmailType(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());

        $token = $factory->makeConfirmOldMailToken(3);

        self::assertSame(UserToken::TYPE_CONFIRM_OLD_EMAIL, $token->getType());
    }

    public function testMakeRecoveryTokenUsesRecoveryType(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());

        $token = $factory->makeRecoveryToken(3);

        self::assertSame(UserToken::TYPE_RECOVERY, $token->getType());
    }
}
