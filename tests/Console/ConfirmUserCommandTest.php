<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\ConfirmUserCommand;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

#[AllowMockObjectsWithoutExpectations]
final class ConfirmUserCommandTest extends TestCase
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

    public function testExecuteByEmail(): void
    {
        $this->createUser(createdAt: 1000);

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'test@example.com'],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->willReturn(true);

        $command = $this->createCommand($confirmationService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteById(): void
    {
        $user = $this->createUser(createdAt: 1000);

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', (string) $user->getId()],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->willReturn(true);

        $command = $this->createCommand($confirmationService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteByUsername(): void
    {
        $this->createUser(createdAt: 1000);

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', null],
            ['username', 'testuser'],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->willReturn(true);

        $command = $this->createCommand($confirmationService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteConfirmationFails(): void
    {
        $user = $this->createUser(createdAt: 1000);

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', (string) $user->getId()],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->willReturn(false);

        $command = $this->createCommand($confirmationService);
        $result = $command->run($input, $output);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithNonExistentUser(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'missing@example.com'],
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

    private function createCommand(?ConfirmationService $confirmationService = null): ConfirmUserCommand
    {
        return new ConfirmUserCommand(
            $confirmationService ?? $this->createMock(ConfirmationService::class),
        );
    }
}
