<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\DeleteUserCommand;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class DeleteUserCommandTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    private EventCaptureDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventDispatcher = new EventCaptureDispatcher();
    }

    public static function identifierProvider(): iterable
    {
        yield 'by email' => ['email', 'del@example.com'];
        yield 'by id' => ['id', 'self'];
        yield 'by username' => ['username', 'testuser'];
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

    #[DataProvider('identifierProvider')]
    public function testExecuteByIdentifier(string $option, string $value): void
    {
        $user = $this->createUser('testuser', 'del@example.com', createdAt: 1000);

        $map = ['id' => null, 'email' => null, 'username' => null];
        $map[$option] = $value === 'self' ? (string) $user->getId() : $value;

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', $map['id']],
            ['email', $map['email']],
            ['username', $map['username']],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('writeln');

        $command = $this->createCommand();
        $result = $command->run($input, $output);

        self::assertSame(ExitCode::OK, $result);
        self::assertNull(User::findByUsername('testuser'));
        self::assertNull(User::findByEmail('del@example.com'));
        // Deletion dispatches UserEvent::DELETE so packages (e.g. yiirocks/voyti-2fa) can cascade.
        $event = $this->eventDispatcher->getEvent(UserEvent::class);
        self::assertInstanceOf(UserEvent::class, $event);
        self::assertSame(UserEvent::DELETE, $event->getType());
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

        self::assertSame(ExitCode::NOUSER, $result);
    }

    private function createCommand(): DeleteUserCommand
    {
        return new DeleteUserCommand($this->eventDispatcher);
    }
}
