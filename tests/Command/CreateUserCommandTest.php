<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Command;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YiiRocks\Voyti\Command\CreateUserCommand;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;

final class CreateUserCommandTest extends TestCase
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
        $command = $this->createCommand();

        self::assertSame('voyti:create', $command->getName());
        self::assertSame('Create a new user', $command->getDescription());
    }

    public function testExecuteAssignsRoleWhenRoleOptionProvided(): void
    {
        $userRepository = new UserRepository();
        $authManager = $this->createMock(ManagerInterface::class);
        $capturedUserId = null;
        $authManager->expects(self::once())
            ->method('assign')
            ->with('admin', self::callback(function (mixed $userId) use (&$capturedUserId): bool {
                $capturedUserId = $userId;
                return true;
            }));

        $tester = new CommandTester(new CreateUserCommand(
            $this->createRealService(),
            $userRepository,
            $authManager,
        ));

        $exitCode = $tester->execute([
            'email' => 'dave@example.com',
            'username' => 'dave',
            '--role' => 'admin',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Role assigned: admin', $tester->getDisplay());

        $actualUser = $userRepository->findByEmail('dave@example.com');
        self::assertSame((int) $actualUser->getId(), $capturedUserId);
    }

    public function testExecuteFailsWhenEmailArgumentIsEmptyString(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['email' => '', 'username' => 'alice']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Missing required arguments.', $tester->getDisplay());
    }

    public function testExecuteFailsWhenEmailArgumentMissing(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['username' => 'alice']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertSame(
            "Missing required arguments.\n"
            . "\n"
            . "Usage: voyti:create [options] [--] <email> <username>\n"
            . "\n"
            . "  email      Email\n"
            . "  username   Username\n"
            . "\n"
            . "Options:\n"
            . "  -p, --password   Password (auto-generated if omitted)\n"
            . "  -r, --role       Role to assign\n",
            $this->normalizeLineEndings($tester->getDisplay()),
        );
    }

    public function testExecuteFailsWhenNoArgumentsProvided(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Missing required arguments.', $tester->getDisplay());
    }

    public function testExecuteFailsWhenUsernameArgumentMissing(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['email' => 'alice@example.com']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Missing required arguments.', $tester->getDisplay());
    }

    public function testExecuteGeneratesRandomPasswordWhenPasswordOptionOmitted(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'email' => 'erin@example.com',
            'username' => 'erin',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $this->normalizeLineEndings($tester->getDisplay());
        self::assertMatchesRegularExpression('/^Password: [0-9a-f]+$/m', $display);
        preg_match('/^Password: ([0-9a-f]+)$/m', $display, $matches);
        self::assertSame(16, strlen($matches[1]));
    }

    public function testExecuteReportsFailureMessageWhenEmailAlreadyExists(): void
    {
        $userRepository = new UserRepository();
        $existing = new User();
        $existing->setUsername('existing');
        $existing->setEmail('taken@example.com');
        $existing->setPasswordHash('hash');
        $existing->setAuthKey('key');
        $existing->setCreatedAt(time());
        $existing->setUpdatedAt(time());
        $existing->save();

        $tester = new CommandTester(new CreateUserCommand(
            $this->createRealService(),
            $userRepository,
            $this->createStub(ManagerInterface::class),
        ));

        $exitCode = $tester->execute([
            'email' => 'taken@example.com',
            'username' => 'newname',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("Email already exists\n", $this->normalizeLineEndings($tester->getDisplay()));
    }

    public function testExecuteReportsFailureMessageWhenUsernameAlreadyExists(): void
    {
        $userRepository = new UserRepository();
        $existing = new User();
        $existing->setUsername('taken');
        $existing->setEmail('original@example.com');
        $existing->setPasswordHash('hash');
        $existing->setAuthKey('key');
        $existing->setCreatedAt(time());
        $existing->setUpdatedAt(time());
        $existing->save();

        $tester = new CommandTester(new CreateUserCommand(
            $this->createRealService(),
            $userRepository,
            $this->createStub(ManagerInterface::class),
        ));

        $exitCode = $tester->execute([
            'email' => 'new@example.com',
            'username' => 'taken',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("Username already exists\n", $this->normalizeLineEndings($tester->getDisplay()));
    }

    public function testExecuteSkipsRoleAssignmentWhenRoleOptionOmitted(): void
    {
        $userRepository = new UserRepository();
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::never())->method('assign');

        $tester = new CommandTester(new CreateUserCommand(
            $this->createRealService(),
            $userRepository,
            $authManager,
        ));

        $exitCode = $tester->execute([
            'email' => 'grace@example.com',
            'username' => 'grace',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('Role assigned', $tester->getDisplay());
    }

    public function testExecuteUsesExplicitPasswordWhenProvided(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'email' => 'carol@example.com',
            'username' => 'carol',
            '--password' => 'my-explicit-password',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('User created: carol (carol@example.com)', $display);
        self::assertStringContainsString('Password: my-explicit-password', $display);
    }

    public function testGetUserIdUsesZeroFallbackWhenUserHasNoId(): void
    {
        // A user that was never persisted has a null id; the private getUserId()
        // fallback must be exactly 0 (not -1 or 1). save() always populates the
        // primary key on insert, so the null branch cannot be observed by going
        // through execute(); we invoke the private method directly via reflection.
        $user = new User();
        $user->setUsername('unsaved');
        $user->setEmail('unsaved@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        self::assertNull($user->getId());

        $command = $this->createCommand();

        $method = new \ReflectionMethod(CreateUserCommand::class, 'getUserId');
        $result = $method->invoke($command, $user);

        self::assertSame(0, $result);
    }

    private function createCommand(): CreateUserCommand
    {
        return new CreateUserCommand(
            $this->createRealService(),
            new UserRepository(),
            $this->createStub(ManagerInterface::class),
        );
    }

    private function createRealService(): CreateService
    {
        $mailService = new MailService(
            new class implements MailerInterface {
                #[\Override]
                public function send(MessageInterface $message): void
                {
                }

                #[\Override]
                public function sendMultiple(array $messages): SendResults
                {
                    return new SendResults([], []);
                }
            },
            dirname(__DIR__, 2) . '/src/resources/mail',
            $this->getTranslator(),
            $this->createStub(UrlGeneratorInterface::class),
        );

        return new CreateService(
            new UserRepository(),
            $mailService,
            $this->createStub(EventDispatcherInterface::class),
            new PasswordHasher(),
            new ModuleConfig(enableEmailConfirmation: false),
        );
    }

    private function createTester(): CommandTester
    {
        return new CommandTester($this->createCommand());
    }
}
