<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;
use YiiRocks\Voyti\Console\ConfirmUserCommand;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class ConfirmUserCommandTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;
    use UserFactoryTrait;

    public static function identifierProvider(): iterable
    {
        yield 'by email' => ['--email', 'test@example.com'];
        yield 'by id' => ['--id', 'self'];
        yield 'by username' => ['--username', 'testuser'];
    }

    public function testConfiguration(): void
    {
        $command = $this->createCommand();

        self::assertSame('voyti:confirm', $command->getName());
        self::assertSame('Confirm a user', $command->getDescription());
    }

    #[DataProvider('identifierProvider')]
    public function testExecuteByIdentifier(string $option, string $value): void
    {
        $user = $this->createUser(createdAt: 1000);

        $tester = new CommandTester($this->createCommand($this->createConfirmationService()));
        $result = $tester->execute([$option => $value === 'self' ? (string) $user->getId() : $value]);

        self::assertSame(ExitCode::OK, $result);
        self::assertStringContainsString('User confirmed.', $tester->getDisplay());
    }

    public function testExecuteConfirmationFails(): void
    {
        $user = $this->createUser(createdAt: 1000, confirmedAt: 1000);

        $tester = new CommandTester($this->createCommand($this->createConfirmationService()));
        $result = $tester->execute(['--id' => (string) $user->getId()]);

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $result);
        self::assertStringContainsString('Unable to confirm user.', $tester->getDisplay());
    }

    public function testExecuteWithNonExistentUser(): void
    {
        $tester = new CommandTester($this->createCommand());
        $result = $tester->execute(['--email' => 'missing@example.com']);

        self::assertSame(ExitCode::NOUSER, $result);
        self::assertStringContainsString('User not found.', $tester->getDisplay());
    }

    private function createCommand(?ConfirmationService $confirmationService = null): ConfirmUserCommand
    {
        return new ConfirmUserCommand(
            $confirmationService ?? $this->createConfirmationService(),
        );
    }

    private function createConfirmationService(): ConfirmationService
    {
        return new ConfirmationService(
            new EventCaptureDispatcher(),
            new UserTokenFactory(),
            $this->createMailService(new MailCapture()),
        );
    }
}
