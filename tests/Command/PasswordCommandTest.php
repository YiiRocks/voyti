<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Command\PasswordCommand;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Repository\UserRepository;
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

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findByEmail')->with('pw_reset@example.com')->willReturn($user);

        $command = $this->createCommand($userRepository);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
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
            ['id', '3'],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))->method('writeln');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findById')->with(3)->willReturn($user);

        $command = $this->createCommand($userRepository);
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

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findByUsername')->with('pw_user')->willReturn($user);

        $command = $this->createCommand($userRepository);
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

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findByEmail')->with('ghost@example.com')->willReturn(null);

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
        ?PasswordHasher $passwordHasher = null,
    ): PasswordCommand {
        return new PasswordCommand(
            $userRepository ?? $this->createMock(UserRepository::class),
            $passwordHasher ?? new PasswordHasher(),
        );
    }
}
