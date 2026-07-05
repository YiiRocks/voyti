<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YiiRocks\Voyti\Command\DeleteUserCommand;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class DeleteUserCommandTest extends TestCase
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
        $db->createCommand('CREATE TABLE {{%user_profile}} (
            user_id INTEGER NOT NULL PRIMARY KEY,
            name VARCHAR(255),
            public_email VARCHAR(255),
            gravatar_email VARCHAR(255),
            location VARCHAR(255),
            website VARCHAR(255),
            bio TEXT,
            timezone VARCHAR(40)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testCommandNameAndDescription(): void
    {
        $command = new DeleteUserCommand(new UserRepository());

        self::assertSame('voyti:delete', $command->getName());
        self::assertSame('Delete a user', $command->getDescription());
    }

    public function testExecuteDeletesUserAndCascadesProfileFoundByEmail(): void
    {
        $user = $this->createUser('alice', 'alice@example.com');
        $userId = (int) $user->getId();
        $this->createProfile($userId);

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--email' => 'alice@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('User deleted.', $tester->getDisplay());
        self::assertNull(User::query()->findByPk($user->getId()));
        self::assertNull((new UserProfileRepository())->findByUserId($userId));
    }

    public function testExecuteDeletesUserFoundById(): void
    {
        $user = $this->createUser('bob', 'bob@example.com');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--id' => (string) $user->getId()]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('User deleted.', $tester->getDisplay());
        self::assertNull(User::query()->findByPk($user->getId()));
    }

    public function testExecuteDeletesUserFoundByUsername(): void
    {
        $user = $this->createUser('carol', 'carol@example.com');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--username' => 'carol']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('User deleted.', $tester->getDisplay());
        self::assertNull(User::query()->findByPk($user->getId()));
    }

    public function testExecuteShowsUsageWhenNoOptionProvided(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(
            "No identifying option provided.\n"
            . "\n"
            . "Usage: voyti:delete [options]\n"
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
        $exitCode = $tester->execute(['--username' => 'nobody']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('User not found.', $tester->getDisplay());
    }

    private function createProfile(int $userId): void
    {
        $profile = new UserProfile();
        $profile->setUserId($userId);
        $profile->save();
    }

    private function createTester(): CommandTester
    {
        return new CommandTester(new DeleteUserCommand(new UserRepository()));
    }

    private function createUser(string $username, string $email): User
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
