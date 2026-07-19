<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\DeleteUserCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

#[AllowMockObjectsWithoutExpectations]
final class DeleteUserCommandTest extends TestCase
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

        self::assertSame('voyti:delete', $command->getName());
        self::assertSame('Delete a user', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('email'));
        self::assertTrue($command->getDefinition()->hasOption('username'));
        self::assertTrue($command->getDefinition()->hasOption('id'));
    }

    public function testExecuteByEmail(): void
    {
        $this->createUser('testuser', 'del@example.com', createdAt: 1000);

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'del@example.com'],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertNull(User::findByEmail('del@example.com'));
    }

    public function testExecuteById(): void
    {
        $user = $this->createUser('testuser', 'test@example.com', createdAt: 1000);

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', (string) $user->getId()],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertNull(User::findByUsername('testuser'));
    }

    public function testExecuteByUsername(): void
    {
        $this->createUser('delete_me', 'delete@example.com', createdAt: 1000);

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', null],
            ['username', 'delete_me'],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertNull(User::findByUsername('delete_me'));
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

    private function createCommand(): DeleteUserCommand
    {
        return new DeleteUserCommand();
    }
}
