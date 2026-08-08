<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\PasswordCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class PasswordCommandTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public static function failureProvider(): iterable
    {
        yield 'non-existent user' => [null, 'ghost@example.com', null, ExitCode::NOUSER, 1];
        yield 'no options' => [null, null, null, ExitCode::USAGE, 8];
    }

    public static function identifierProvider(): iterable
    {
        yield 'by email' => ['email', 'pw_reset@example.com', 'testuser'];
        yield 'by id' => ['id', 'self', 'testuser'];
        yield 'by username' => ['username', 'pw_user', 'pw_user'];
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

        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $command = $this->createCommand(config: $config);
        $result = $command->run($input, $output);

        self::assertSame(ExitCode::OK, $result);
        $history = UserPasswordHistory::findByUserId((int) $user->getId());
        self::assertCount(1, $history);
    }

    #[DataProvider('identifierProvider')]
    public function testExecuteByIdentifier(string $option, string $value, string $username): void
    {
        $user = $this->createUser(
            username: $username,
            email: 'pw_reset@example.com',
            passwordHash: 'old_hash',
            createdAt: 1000,
        );

        $map = ['id' => null, 'email' => null, 'username' => null];
        $map[$option] = $value === 'self' ? (string) $user->getId() : $value;

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', $map['id']],
            ['email', $map['email']],
            ['username', $map['username']],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))->method('writeln');

        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->expects(self::once())->method('generate')->with(16)->willReturn('generated-secret');

        $command = $this->createCommand(passwordGenerator: $passwordGenerator);
        $result = $command->run($input, $output);

        self::assertSame(ExitCode::OK, $result);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue(password_verify('generated-secret', $reloaded->getPasswordHash()));
        self::assertNotNull($reloaded->getPasswordChangedAt());
        self::assertGreaterThan(1000, $reloaded->getUpdatedAt());
    }

    #[DataProvider('failureProvider')]
    public function testExecuteFailure(?string $id, ?string $email, ?string $username, int $expectedCode, int $writelnCount): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', $id],
            ['email', $email],
            ['username', $username],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly($writelnCount))->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame($expectedCode, $result);
    }

    private function createCommand(
        ?PasswordGeneratorInterface $passwordGenerator = null,
        ?VoytiConfig $config = null,
    ): PasswordCommand {
        $passwordHasher = TestPasswordHasherFactory::create();

        return new PasswordCommand(
            $passwordGenerator ?? new RandomPasswordGenerator(),
            new PasswordHistoryService($passwordHasher, $config ?? VoytiConfigFactory::create()),
        );
    }
}
