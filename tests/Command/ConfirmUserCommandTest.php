<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Command;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YiiRocks\Voyti\Command\ConfirmUserCommand;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class ConfirmUserCommandTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            unconfirmed_email VARCHAR(255),
            registration_ip VARCHAR(45),
            flags INTEGER NOT NULL DEFAULT 0,
            confirmed_at INTEGER,
            blocked_at INTEGER,
            updated_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            last_login_at INTEGER,
            auth_tf_key VARCHAR(64),
            auth_tf_enabled INTEGER DEFAULT 0,
            password_changed_at INTEGER,
            last_login_ip VARCHAR(45),
            gdpr_deleted INTEGER DEFAULT 0,
            gdpr_consent INTEGER DEFAULT 0,
            gdpr_consent_date INTEGER,
            auth_tf_type VARCHAR(20)
        )')->execute();
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
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testCommandNameAndDescription(): void
    {
        $userRepository = new UserRepository();
        $confirmationService = new ConfirmationService(
            $this->createStub(EventDispatcherInterface::class),
            new UserTokenRepository(),
        );
        $command = new ConfirmUserCommand($userRepository, $confirmationService);

        self::assertSame('voyti:confirm', $command->getName());
        self::assertSame('Confirm a user', $command->getDescription());
    }

    public function testExecuteConfirmsUserFoundByEmail(): void
    {
        $user = $this->createUnconfirmedUser('alice', 'alice@example.com');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--email' => 'alice@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('User confirmed.', $tester->getDisplay());
        $reloaded = User::query()->findByPk($user->getId());
        self::assertTrue($reloaded->isConfirmed());
    }

    public function testExecuteConfirmsUserFoundById(): void
    {
        $user = $this->createUnconfirmedUser('bob', 'bob@example.com');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--id' => (string) $user->getId()]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('User confirmed.', $tester->getDisplay());
        $reloaded = User::query()->findByPk($user->getId());
        self::assertTrue($reloaded->isConfirmed());
    }

    public function testExecuteConfirmsUserFoundByUsername(): void
    {
        $user = $this->createUnconfirmedUser('carol', 'carol@example.com');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--username' => 'carol']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('User confirmed.', $tester->getDisplay());
        $reloaded = User::query()->findByPk($user->getId());
        self::assertTrue($reloaded->isConfirmed());
    }

    public function testExecuteFailsWhenUserAlreadyConfirmed(): void
    {
        $user = $this->createUnconfirmedUser('dave', 'dave@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--email' => 'dave@example.com']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Unable to confirm user.', $tester->getDisplay());
    }

    public function testExecuteShowsUsageWhenNoOptionProvided(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(
            "No identifying option provided.\n"
            . "\n"
            . "Usage: voyti:confirm [options]\n"
            . "\n"
            . "Options:\n"
            . "  --email=<email>        Email\n"
            . "  --username=<username>  Username\n"
            . "  --id=<id>              ID\n",
            $this->normalizeLineEndings($tester->getDisplay()),
        );
    }

    public function testExecuteShowsUserNotFoundWhenNoMatch(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--email' => 'nobody@example.com']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('User not found.', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        $userRepository = new UserRepository();
        $confirmationService = new ConfirmationService(
            $this->createStub(EventDispatcherInterface::class),
            new UserTokenRepository(),
        );

        return new CommandTester(new ConfirmUserCommand($userRepository, $confirmationService));
    }

    private function createUnconfirmedUser(string $username, string $email): User
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
