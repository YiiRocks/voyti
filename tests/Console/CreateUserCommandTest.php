<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\CreateUserCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use Yiisoft\Rbac\ManagerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CreateUserCommandTest extends TestCase
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

    public function testExecuteWithEmptyEmail(): void
    {
        $input = $this->createMock(InputInterface::class);
        $matcher = self::exactly(2);
        $input
            ->expects($matcher)
            ->method('getArgument')
            ->willReturnCallback(function (string $name) use ($matcher): mixed {
                return match ($matcher->numberOfInvocations()) {
                    1 => '',
                    2 => 'testuser',
                    default => '',
                };
            });

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::atLeast(5))->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::INVALID, $result);
    }

    public function testExecuteWithFailure(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input
            ->expects(self::exactly(2))
            ->method('getArgument')
            ->willReturnMap([
                ['email', 'user@example.com'],
                ['username', 'testuser'],
            ]);
        $input
            ->expects(self::once())
            ->method('getOption')
            ->with('password')
            ->willReturn(null);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $createService = $this->createMock(CreateService::class);
        $createService
            ->expects(self::once())
            ->method('run')
            ->willReturn(ServiceResult::failure('Email already exists'));

        $command = $this->createCommand(userCreateService: $createService);
        $result = $command->run($input, $output);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithMissingArguments(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getArgument')->willReturnMap([
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::atLeast(5))->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(Command::INVALID, $result);
    }

    public function testExecuteWithRoleAssignment(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input
            ->expects(self::exactly(2))
            ->method('getArgument')
            ->willReturnMap([
                ['email', 'user@example.com'],
                ['username', 'testuser'],
            ]);

        $matcher = self::exactly(2);
        $input
            ->expects($matcher)
            ->method('getOption')
            ->willReturnCallback(function (string $name) use ($matcher): mixed {
                return match ($matcher->numberOfInvocations()) {
                    1 => null,
                    2 => 'admin',
                    default => '',
                };
            });

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(3))->method('writeln');

        $createService = $this->createMock(CreateService::class);
        $createService
            ->expects(self::once())
            ->method('run')
            ->willReturn(ServiceResult::success());

        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('user@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager
            ->expects(self::once())
            ->method('assign')
            ->with('admin', self::anything());

        $command = $this->createCommand(
            userCreateService: $createService,
            authManager: $authManager,
        );
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithSpecifiedPassword(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input
            ->expects(self::exactly(2))
            ->method('getArgument')
            ->willReturnMap([
                ['email', 'user@example.com'],
                ['username', 'testuser'],
            ]);
        $input
            ->expects(self::exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['password', 'my_secret_pass'],
                ['role', null],
            ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))->method('writeln');

        $createService = $this->createMock(CreateService::class);
        $createService
            ->expects(self::once())
            ->method('run')
            ->with('user@example.com', 'testuser', 'my_secret_pass')
            ->willReturn(ServiceResult::success());

        $command = $this->createCommand(userCreateService: $createService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithSuccess(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input
            ->expects(self::exactly(2))
            ->method('getArgument')
            ->willReturnMap([
                ['email', 'user@example.com'],
                ['username', 'testuser'],
            ]);
        $input
            ->expects(self::exactly(2))
            ->method('getOption')
            ->willReturnMap([
                ['password', null],
                ['role', null],
            ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))->method('writeln');

        $createService = $this->createMock(CreateService::class);
        $createService
            ->expects(self::once())
            ->method('run')
            ->with('user@example.com', 'testuser', self::callback('is_string'))
            ->willReturn(ServiceResult::success());

        $command = $this->createCommand(userCreateService: $createService);
        $result = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }
    private function createCommand(
        ?CreateService $userCreateService = null,
        ?ManagerInterface $authManager = null,
        ?PasswordGeneratorInterface $passwordGenerator = null,
    ): CreateUserCommand {
        return new CreateUserCommand(
            $userCreateService ?? $this->createMock(CreateService::class),
            $authManager ?? $this->createMock(ManagerInterface::class),
            $passwordGenerator ?? new RandomPasswordGenerator(),
        );
    }
}
