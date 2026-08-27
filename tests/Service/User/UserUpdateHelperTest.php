<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Event\User\AfterAccountUpdateEvent;
use YiiRocks\Voyti\Event\User\BeforeAccountUpdateEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserUpdateHelper;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FixedClock;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

#[AllowMockObjectsWithoutExpectations]
final class UserUpdateHelperTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public static function changedFieldsProvider(): iterable
    {
        yield 'nothing changed' => ['same', 'same@example.com', '', []];
        yield 'username changed' => ['changed', 'same@example.com', '', ['username']];
        yield 'email changed' => ['same', 'changed@example.com', '', ['email']];
        yield 'password provided' => ['same', 'same@example.com', 'newpassword', ['password']];
        yield 'null username is not a change' => [null, 'same@example.com', '', []];
        yield 'null email is not a change' => ['same', null, '', []];
        yield 'everything changed' => ['changed', 'changed@example.com', 'newpassword', ['username', 'email', 'password']];
    }

    public function testApply(): void
    {
        // Mutates, persists via a clock-stamped save, and dispatches Before/After with the changed
        // field list.
        $user = $this->createUser(createdAt: 1000);
        $dispatcher = new EventCaptureDispatcher();
        $clock = new FixedClock(new DateTimeImmutable('@1750000000'));
        $mutated = false;

        $this->createHelper($dispatcher, $clock)->apply(
            $user,
            ['username'],
            static function (User $user) use (&$mutated): void {
                $user->setUsername('mutated');
                $mutated = true;
            },
            '',
        );

        self::assertTrue($mutated);
        $updated = User::findById((int) $user->getId());
        self::assertNotNull($updated);
        self::assertSame('mutated', $updated->getUsername());
        self::assertSame(1750000000, $updated->getUpdatedAt());
        $before = $dispatcher->getEvent(BeforeAccountUpdateEvent::class);
        self::assertInstanceOf(BeforeAccountUpdateEvent::class, $before);
        self::assertSame(['username'], $before->getChangedFields());
        $after = $dispatcher->getEvent(AfterAccountUpdateEvent::class);
        self::assertInstanceOf(AfterAccountUpdateEvent::class, $after);
        self::assertSame(['username'], $after->getChangedFields());

        // With no changed fields, the mutation and save still happen, but no events fire.
        $dispatcher = new EventCaptureDispatcher();
        $user = $this->createUser(username: 'untouched', email: 'untouched@example.com');
        $ran = false;

        $this->createHelper($dispatcher, $clock)->apply(
            $user,
            [],
            static function (User $user) use (&$ran): void {
                $ran = true;
            },
            '',
        );

        self::assertTrue($ran);
        self::assertFalse($dispatcher->hasEvent(BeforeAccountUpdateEvent::class));
        self::assertFalse($dispatcher->hasEvent(AfterAccountUpdateEvent::class));

        // A non-empty password routes the save through PasswordHistoryService::applyPasswordChange()
        // instead of the plain setUpdatedAt()+save() path.
        $user = $this->createUser(username: 'passworduser', email: 'passworduser@example.com');
        $originalHash = $user->getPasswordHash();

        $this->createHelper()->apply($user, ['password'], static function (User $_user): void {}, 'newpassword123');

        $updated = User::findById((int) $user->getId());
        self::assertNotNull($updated);
        self::assertNotSame($originalHash, $updated->getPasswordHash());
        self::assertNotNull($updated->getPasswordChangedAt());

        // A BeforeAccountUpdateEvent listener throwing ActionPreventedException propagates and
        // leaves the user unmutated - the exception is thrown before $mutate() ever runs.
        $user = $this->createUser(username: 'original', email: 'original@example.com');
        $throwingDispatcher = $this->createMock(EventDispatcherInterface::class);
        $throwingDispatcher->method('dispatch')->willThrowException(new ActionPreventedException('Update blocked'));
        $mutated = false;

        try {
            $this->createHelper($throwingDispatcher)->apply(
                $user,
                ['username'],
                static function (User $user) use (&$mutated): void {
                    $mutated = true;
                },
                '',
            );
            self::fail('Expected an ActionPreventedException to be thrown.');
        } catch (ActionPreventedException $exception) {
            self::assertSame('Update blocked', $exception->getMessage());
        }

        self::assertFalse($mutated);
        $unchanged = User::findById((int) $user->getId());
        self::assertNotNull($unchanged);
        self::assertSame('original', $unchanged->getUsername());
    }

    #[DataProvider('changedFieldsProvider')]
    public function testChangedFields(?string $username, ?string $email, string $password, array $expected): void
    {
        $user = $this->createUser(username: 'same', email: 'same@example.com');

        self::assertSame($expected, $this->createHelper()->changedFields($user, $username, $email, $password));
    }

    private function createHelper(
        ?EventDispatcherInterface $dispatcher = null,
        ?ClockInterface $clock = null,
    ): UserUpdateHelper {
        $config = VoytiConfigFactory::create();
        $passwordHasher = TestPasswordHasherFactory::create();

        return new UserUpdateHelper(
            $clock ?? new SystemClock(),
            $dispatcher ?? new EventCaptureDispatcher(),
            new PasswordHistoryService($passwordHasher, $config),
        );
    }
}
