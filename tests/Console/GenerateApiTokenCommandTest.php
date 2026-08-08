<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\User\ApiTokenService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class GenerateApiTokenCommandTest extends DatabaseTestCase
{
    public static function failureProvider(): iterable
    {
        yield 'non-existent user' => [null, 'ghost@example.com', null, ExitCode::NOUSER, 1];
        yield 'no options' => [null, null, null, ExitCode::USAGE, 8];
    }

    public function testConfigureSetsCommandMetadata(): void
    {
        $command = $this->createCommand();

        self::assertSame('voyti:api-token:generate', $command->getName());
        self::assertSame('Generate an API access token for a user', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('email'));
        self::assertTrue($command->getDefinition()->hasOption('username'));
        self::assertTrue($command->getDefinition()->hasOption('id'));
    }

    public function testExecuteByUsernameGeneratesToken(): void
    {
        $user = new User();
        $user->setUsername('apiuser');
        $user->setEmail('api@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);
        $user->save();

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', null],
            ['username', 'apiuser'],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(3))->method('writeln');

        $command = $this->createCommand(new ApiTokenService());
        $result = $command->run($input, $output);

        self::assertSame(ExitCode::OK, $result);
        self::assertCount(1, UserToken::findByUserId((int) $user->getId()));
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
        $command = $this->createCommand(new ApiTokenService());
        $result = $command->run($input, $output);

        self::assertSame($expectedCode, $result);
    }

    private function createCommand(?ApiTokenService $apiTokenService = null): GenerateApiTokenCommand
    {
        return new GenerateApiTokenCommand(
            $apiTokenService ?? new ApiTokenService(),
        );
    }
}
