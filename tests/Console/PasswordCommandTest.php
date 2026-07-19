<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\PasswordCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

#[AllowMockObjectsWithoutExpectations]
final class PasswordCommandTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testConfigureSetsCommandMetadata(): void
    {
        $command = $this->createCommand();

        self::assertSame('voyti:password', $command->getName());
        self::assertSame('Reset a user password', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('email'));
        self::assertTrue($command->getDefinition()->hasOption('username'));
        self::assertTrue($command->getDefinition()->hasOption('id'));
    }

    public function testExecuteByEmail(): void
    {
        $user = $this->createUser(
            username: 'testuser',
            email: 'pw_reset@example.com',
            passwordHash: 'old_hash',
            createdAt: 1000,
        );

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'pw_reset@example.com'],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))->method('writeln');

        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->expects(self::once())->method('generate')->with(16)->willReturn('generated-secret');

        $command = $this->createCommand(passwordGenerator: $passwordGenerator);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue(password_verify('generated-secret', $reloaded->getPasswordHash()));
        self::assertNotNull($reloaded->getPasswordChangedAt());
        self::assertGreaterThan(1000, $reloaded->getUpdatedAt());
    }

    public function testExecuteByEmailRecordsPasswordHistory(): void
    {
        $user = $this->createUser(
            username: 'historyuser',
            email: 'pw_history@example.com',
            passwordHash: 'old_hash',
            createdAt: 1000,
        );

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'pw_history@example.com'],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);

        $config = new ModuleConfig(enablePasswordExpiration: true);
        $command = $this->createCommand(config: $config);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
        $history = UserPasswordHistory::findByUserId((int) $user->getId());
        self::assertCount(1, $history);
    }

    public function testExecuteById(): void
    {
        $user = $this->createUser(
            username: 'testuser',
            email: 'test@example.com',
            passwordHash: 'old_hash',
            createdAt: 1000,
        );

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', (string) $user->getId()],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteByUsername(): void
    {
        $user = $this->createUser(
            username: 'pw_user',
            email: 'pw@example.com',
            passwordHash: 'old_hash',
            createdAt: 1000,
        );

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', null],
            ['username', 'pw_user'],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithNonExistentUser(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'ghost@example.com'],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithNoOptions(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::atLeast(4))->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::FAILURE, $result);
    }

    private function createCommand(
        ?PasswordGeneratorInterface $passwordGenerator = null,
        ?ModuleConfig $config = null,
    ): PasswordCommand {
        $passwordHasher = TestPasswordHasherFactory::create();

        return new PasswordCommand(
            $passwordGenerator ?? new RandomPasswordGenerator(),
            new PasswordHistoryService($passwordHasher, $config ?? new ModuleConfig()),
        );
    }
}
