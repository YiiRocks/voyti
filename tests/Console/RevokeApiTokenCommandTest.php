<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\RevokeApiTokenCommand;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\User\ApiTokenService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class RevokeApiTokenCommandTest extends TestCase
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

    public function testExecuteByUsernameRevokesTokens(): void
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
        $output->expects(self::once())->method('writeln');

        $apiTokenService = $this->createMock(ApiTokenService::class);
        $apiTokenService->expects(self::once())->method('revokeAll')->with($user)->willReturn(2);

        $command = $this->createCommand($apiTokenService);
        $result = $command->run($input, $output);

        self::assertSame(ExitCode::OK, $result);
    }

    public function testExecuteWithNonExistentUserFails(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', 'ghost@example.com'],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $apiTokenService = $this->createMock(ApiTokenService::class);
        $apiTokenService->expects(self::never())->method('revokeAll');

        $command = $this->createCommand($apiTokenService);
        $result = $command->run($input, $output);

        self::assertSame(ExitCode::NOUSER, $result);
    }

    public function testExecuteWithNoOptionsFails(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', null],
            ['email', null],
            ['username', null],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::atLeast(4))->method('writeln');

        $apiTokenService = $this->createMock(ApiTokenService::class);
        $apiTokenService->expects(self::never())->method('revokeAll');

        $command = $this->createCommand($apiTokenService);
        $result = $command->run($input, $output);

        self::assertSame(ExitCode::USAGE, $result);
    }

    private function createCommand(?ApiTokenService $apiTokenService = null): RevokeApiTokenCommand
    {
        return new RevokeApiTokenCommand(
            $apiTokenService ?? $this->createMock(ApiTokenService::class),
        );
    }
}
