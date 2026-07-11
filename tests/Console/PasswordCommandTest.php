<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\PasswordCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use Yiisoft\Security\PasswordHasher;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class PasswordCommandTest extends TestCase
{
    use DatabaseSetupTrait;

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
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('pw_reset@example.com');
        $user->setPasswordHash('old_hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

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

    public function testExecuteById(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('old_hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

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
        $user = new User();
        $user->setUsername('pw_user');
        $user->setEmail('pw@example.com');
        $user->setPasswordHash('old_hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

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
        ?PasswordHasher $passwordHasher = null,
        ?PasswordGeneratorInterface $passwordGenerator = null,
    ): PasswordCommand {
        return new PasswordCommand(
            $passwordHasher ?? new PasswordHasher(),
            $passwordGenerator ?? new RandomPasswordGenerator(),
        );
    }
}
