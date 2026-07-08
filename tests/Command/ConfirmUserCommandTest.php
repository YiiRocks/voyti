<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Command\ConfirmUserCommand;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ConfirmUserCommandTest extends TestCase
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

    public function testExecuteByEmail(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'test@example.com'],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findByEmail')->with('test@example.com')->willReturn($user);

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->with($user)->willReturn(true);

        $command = $this->createCommand($userRepository, $confirmationService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteById(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', '5'],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findById')->with(5)->willReturn($user);

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->with($user)->willReturn(true);

        $command = $this->createCommand($userRepository, $confirmationService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteByUsername(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', null],
            ['username', 'testuser'],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findByUsername')->with('testuser')->willReturn($user);

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->with($user)->willReturn(true);

        $command = $this->createCommand($userRepository, $confirmationService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteConfirmationFails(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', '1'],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findById')->with(1)->willReturn($user);

        $confirmationService = $this->createMock(ConfirmationService::class);
        $confirmationService->expects(self::once())->method('run')->with($user)->willReturn(false);

        $command = $this->createCommand($userRepository, $confirmationService);
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

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findByEmail')->with('missing@example.com')->willReturn(null);

        $command = $this->createCommand($userRepository);
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
        ?UserRepository $userRepository = null,
        ?ConfirmationService $confirmationService = null,
    ): ConfirmUserCommand {
        return new ConfirmUserCommand(
            $userRepository ?? $this->createMock(UserRepository::class),
            $confirmationService ?? $this->createMock(ConfirmationService::class),
        );
    }
}
