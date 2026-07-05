<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YiiRocks\Voyti\Command\PasswordCommand;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Security\PasswordHasher;

final class PasswordCommandTest extends TestCase
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
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testCommandNameAndDescription(): void
    {
        $command = new PasswordCommand(new UserRepository(), new PasswordHasher());

        self::assertSame('voyti:password', $command->getName());
        self::assertSame('Reset a user password', $command->getDescription());
    }

    public function testExecuteResetsPasswordForUserFoundByEmail(): void
    {
        $user = $this->createUser('alice', 'alice@example.com', 'old-hash');
        $user->setUpdatedAt(1);
        $user->save();

        $before = time();
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--email' => 'alice@example.com']);
        $after = time();

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $this->normalizeLineEndings($tester->getDisplay());
        self::assertStringContainsString('Password reset.', $display);

        self::assertMatchesRegularExpression('/^New password: [0-9a-f]+$/m', $display);
        preg_match('/^New password: ([0-9a-f]+)$/m', $display, $matches);
        self::assertSame(16, strlen($matches[1]));

        $reloaded = User::query()->findByPk($user->getId());
        self::assertNotSame('old-hash', $reloaded->getPasswordHash());
        self::assertNotNull($reloaded->getPasswordChangedAt());
        self::assertGreaterThanOrEqual($before, $reloaded->getUpdatedAt());
        self::assertLessThanOrEqual($after, $reloaded->getUpdatedAt());
    }

    public function testExecuteResetsPasswordForUserFoundById(): void
    {
        $user = $this->createUser('bob', 'bob@example.com', 'old-hash');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--id' => (string) $user->getId()]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Password reset.', $tester->getDisplay());
    }

    public function testExecuteResetsPasswordForUserFoundByUsername(): void
    {
        $this->createUser('carol', 'carol@example.com', 'old-hash');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--username' => 'carol']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Password reset.', $tester->getDisplay());
    }

    public function testExecuteShowsUsageWhenNoOptionProvided(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(
            "No identifying option provided.\n"
            . "\n"
            . "Usage: voyti:password [options]\n"
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
        $exitCode = $tester->execute(['--id' => '999']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('User not found.', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        return new CommandTester(new PasswordCommand(new UserRepository(), new PasswordHasher()));
    }

    private function createUser(string $username, string $email, string $passwordHash): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($passwordHash);
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
