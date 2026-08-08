<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Console\Tester\CommandTester;
use YiiRocks\Voyti\Console\CreateUserCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class CreateUserCommandTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;
    use UserFactoryTrait;

    public function testConfigureSetsCommandMetadata(): void
    {
        $command = $this->createCommand();

        self::assertSame('voyti:create', $command->getName());
        self::assertSame('Create a new user', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasArgument('email'));
        self::assertTrue($command->getDefinition()->hasArgument('username'));
        self::assertTrue($command->getDefinition()->hasOption('password'));
        self::assertTrue($command->getDefinition()->hasOption('role'));
    }

    public function testExecuteWithEmptyEmail(): void
    {
        $tester = new CommandTester($this->createCommand());
        $result = $tester->execute(['email' => '', 'username' => 'testuser']);

        self::assertSame(ExitCode::USAGE, $result);
        self::assertStringContainsString('Missing required arguments.', $tester->getDisplay());
    }

    public function testExecuteWithFailure(): void
    {
        $this->createUser(email: 'user@example.com');

        $tester = new CommandTester($this->createCommand(userCreateService: $this->createCreateService()));
        $result = $tester->execute(['email' => 'user@example.com', 'username' => 'testuser']);

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $result);
        self::assertStringContainsString('Email already exists', $tester->getDisplay());
    }

    public function testExecuteWithMissingArguments(): void
    {
        $tester = new CommandTester($this->createCommand());
        $result = $tester->execute([]);

        self::assertSame(ExitCode::USAGE, $result);
        $display = $this->normalizeLineEndings($tester->getDisplay());
        // Substrings spanning the blank separator lines assert those `writeln('')` calls are present.
        self::assertStringContainsString("Missing required arguments.\n\nUsage: voyti:create [options] [--] <email> <username>", $display);
        self::assertStringContainsString("<email> <username>\n\n  email      Email", $display);
        self::assertStringContainsString('  username   Username', $display);
        self::assertStringContainsString("username   Username\n\nOptions:", $display);
        self::assertStringContainsString('-p, --password   Password (auto-generated if omitted)', $display);
        self::assertStringContainsString('-r, --role       Role to assign', $display);
    }

    public function testExecuteWithMissingEmailButUsernameGiven(): void
    {
        // email argument omitted (null) while username is a valid non-empty string: the missing-argument
        // guard must still fire (kills the `||`-to-`&&` mutant that only trips when both are non-strings).
        $tester = new CommandTester($this->createCommand());
        $result = $tester->execute(['username' => 'validuser']);

        self::assertSame(ExitCode::USAGE, $result);
        self::assertStringContainsString('Missing required arguments.', $tester->getDisplay());
    }

    public function testExecuteWithRoleAssignment(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager
            ->expects(self::once())
            ->method('assign')
            ->with('admin', self::anything());

        $tester = new CommandTester($this->createCommand(
            userCreateService: $this->createCreateService(),
            authManager: $authManager,
        ));
        $result = $tester->execute(['email' => 'user@example.com', 'username' => 'testuser', '--role' => 'admin']);

        self::assertSame(ExitCode::OK, $result);
        self::assertStringContainsString('Role assigned: admin', $tester->getDisplay());
        self::assertNotNull(User::findByEmail('user@example.com'));
    }

    public function testExecuteWithSpecifiedPassword(): void
    {
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->expects(self::never())->method('generate');

        $tester = new CommandTester($this->createCommand(
            userCreateService: $this->createCreateService(),
            passwordGenerator: $passwordGenerator,
        ));
        $result = $tester->execute([
            'email' => 'user@example.com',
            'username' => 'testuser',
            '--password' => 'my_secret_pass',
        ]);

        self::assertSame(ExitCode::OK, $result);
        self::assertStringContainsString('User created: testuser (user@example.com)', $tester->getDisplay());
        $user = User::findByEmail('user@example.com');
        self::assertNotNull($user);
        self::assertSame('testuser', $user->getUsername());
        self::assertTrue(TestPasswordHasherFactory::create()->validate('my_secret_pass', $user->getPasswordHash()));
    }

    public function testExecuteWithSuccessGeneratesPassword(): void
    {
        // No --password: the command auto-generates a 16-char password (the length is asserted via the
        // generator's `with(16)` expectation, killing the increment/decrement mutants on that argument).
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->expects(self::once())->method('generate')->with(16)->willReturn('generated-secret');

        $tester = new CommandTester($this->createCommand(
            userCreateService: $this->createCreateService(),
            passwordGenerator: $passwordGenerator,
        ));
        $result = $tester->execute(['email' => 'user@example.com', 'username' => 'testuser']);

        self::assertSame(ExitCode::OK, $result);
        $display = $tester->getDisplay();
        self::assertStringContainsString('User created: testuser (user@example.com)', $display);
        self::assertStringContainsString('Password: generated-secret', $display);
        $user = User::findByEmail('user@example.com');
        self::assertNotNull($user);
        self::assertTrue(TestPasswordHasherFactory::create()->validate('generated-secret', $user->getPasswordHash()));
    }

    private function createCommand(
        ?CreateService $userCreateService = null,
        ?ManagerInterface $authManager = null,
        ?PasswordGeneratorInterface $passwordGenerator = null,
    ): CreateUserCommand {
        return new CreateUserCommand(
            $userCreateService ?? $this->createCreateService(),
            $authManager ?? $this->createMock(ManagerInterface::class),
            $passwordGenerator ?? new RandomPasswordGenerator(),
        );
    }

    private function createCreateService(): CreateService
    {
        $config = VoytiConfigFactory::create();
        $passwordHasher = TestPasswordHasherFactory::create();

        return new CreateService(
            new UserCreationHelper(
                $this->createMailService(new MailCapture()),
                new EventCaptureDispatcher(),
                $passwordHasher,
                $config,
                new PasswordHistoryService($passwordHasher, $config),
            ),
        );
    }
}
