<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Account;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Account\AccountController;
use YiiRocks\Voyti\Event\User\AfterAccountUpdateEvent;
use YiiRocks\Voyti\Event\User\BeforeAccountUpdateEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class AccountControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->validator = $this->mockValidValidator();
    }

    public static function confirmProvider(): iterable
    {
        yield 'invalid code shows failure message' => ['bad-code', false, 'Failed to change email'];
        yield 'valid code shows success message' => ['good-code', true, 'Your email has been changed'];
    }

    public function testAccountGetShowsForm(): void
    {
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->update(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Account settings', $html);
    }

    public function testAccountPostBeforeAccountUpdateEventPreventsUpdate(): void
    {
        // A listener throwing ActionPreventedException from the cancellable BeforeAccountUpdateEvent
        // stops the update before any field is saved, and its message surfaces as a form error.
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'newname', 'email' => 'test@example.com', 'password' => '', 'passwordRepeat' => '']]);

        $user = $this->createUser(username: 'olduser', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $cancellingDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof BeforeAccountUpdateEvent) {
                    throw new ActionPreventedException('Account update blocked by policy', ['username']);
                }

                return $event;
            }
        };
        $html = (string) $this->createController([EventDispatcherInterface::class => $cancellingDispatcher])
            ->update($request)
            ->getBody();

        self::assertStringContainsString('Account update blocked by policy', $html);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('olduser', $updated->getUsername());
    }

    public function testAccountPostUpdatesAndRedirects(): void
    {
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'newname', 'email' => 'test@example.com', 'password' => '', 'passwordRepeat' => '']]);

        // Distinct starting username + a stale updatedAt so the persisted new username and refreshed
        // timestamp are both observable (a dropped setUsername/save/setUpdatedAt would leave them stale).
        $user = $this->createUser(username: 'olduser', passwordHash: $this->passwordHasher->hash('secret'), createdAt: 1000, confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->update($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('user-account', $result->getHeaderLine('Location'));
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('newname', $updated->getUsername());
        $this->assertNotSame(1000, $updated->getUpdatedAt());

        // A username-only change dispatches Before/AfterAccountUpdateEvent with just ['username'].
        /** @var EventCaptureDispatcher $eventDispatcher */
        $eventDispatcher = $this->getTestContainer()->get(EventDispatcherInterface::class);
        $beforeEvent = $eventDispatcher->getEvent(BeforeAccountUpdateEvent::class);
        $this->assertInstanceOf(BeforeAccountUpdateEvent::class, $beforeEvent);
        $this->assertSame(['username'], $beforeEvent->getChangedFields());
        $this->assertSame($user->getId(), $beforeEvent->getUser()->getId());
        $afterEvent = $eventDispatcher->getEvent(AfterAccountUpdateEvent::class);
        $this->assertInstanceOf(AfterAccountUpdateEvent::class, $afterEvent);
        $this->assertSame(['username'], $afterEvent->getChangedFields());
        $this->assertSame('newname', $afterEvent->getUser()->getUsername());
    }

    public function testAccountPostWithNewEmailInvokesChangeStrategy(): void
    {
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'new@example.com', 'password' => '', 'passwordRepeat' => '']]);

        $user = $this->createUser(email: 'old@example.com', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->update($request);

        $this->assertSame(302, $result->getStatusCode());
        // The real EmailChangeService issued a confirmation token for the pending address.
        self::assertNotEmpty(UserToken::findByUserId((int) $user->getId()));
        /** @var EventCaptureDispatcher $eventDispatcher */
        $eventDispatcher = $this->getTestContainer()->get(EventDispatcherInterface::class);
        $this->assertSame(['email'], $eventDispatcher->getEvent(BeforeAccountUpdateEvent::class)?->getChangedFields());
    }

    public function testAccountPostWithNoAccountFieldChangesSkipsEvents(): void
    {
        // Resubmitting the same username/email/blank password changes nothing account-relevant: no
        // Before/AfterAccountUpdateEvent should fire, even though the record is still re-saved.
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => '', 'passwordRepeat' => '']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->update($request);

        $this->assertSame(302, $result->getStatusCode());
        /** @var EventCaptureDispatcher $eventDispatcher */
        $eventDispatcher = $this->getTestContainer()->get(EventDispatcherInterface::class);
        $this->assertFalse($eventDispatcher->hasEvent(BeforeAccountUpdateEvent::class));
        $this->assertFalse($eventDispatcher->hasEvent(AfterAccountUpdateEvent::class));
    }

    public function testAccountPostWithPasswordChange(): void
    {
        // Also changes the username alongside the password, so the resulting changedFields list
        // (['username', 'password']) proves every changed field is reported, not just one.
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'newusername', 'email' => 'test@example.com', 'password' => 'newpassword', 'passwordRepeat' => 'newpassword']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $originalHash = $user->getPasswordHash();
        $this->currentUser->login($user);

        $result = $this->createController()->update($request);

        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('newusername', $updated->getUsername());
        $this->assertNotSame($originalHash, $updated->getPasswordHash());
        $this->assertNotNull($updated->getPasswordChangedAt());
        /** @var EventCaptureDispatcher $eventDispatcher */
        $eventDispatcher = $this->getTestContainer()->get(EventDispatcherInterface::class);
        $this->assertSame(['username', 'password'], $eventDispatcher->getEvent(AfterAccountUpdateEvent::class)?->getChangedFields());
    }

    public function testAccountPostWithPreviouslyUsedPasswordShowsError(): void
    {
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'secret', 'passwordRepeat' => 'secret']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController([VoytiConfig::class => VoytiConfigFactory::create(maxPasswordAge: 90)])
            ->update($request)
            ->getBody();

        // The reused password re-renders the account form and leaves the account unchanged.
        self::assertStringContainsString('Account settings', $html);
        // The error is attached to the password field, so it renders twice: once in the error summary
        // and once beside the password input (a detached or dropped error would render once, or not at all).
        self::assertSame(2, substr_count($html, 'This password has been used recently. Please choose a different one.'));
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('testuser', $updated->getUsername());
    }

    #[DataProvider('confirmProvider')]
    public function testConfirmWithCodeShowsMessage(string $code, bool $serviceResult, string $expectedTitle): void
    {
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        if ($serviceResult) {
            $user->setUnconfirmedEmail('changed@example.com');
            $user->save();
            $token = new UserToken();
            $token->setUserId((int) $user->getId());
            $token->setCode(hash('sha256', $code));
            $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
            $token->setCreatedAt(time());
            $token->save();
        }
        $this->currentUser->login($user);

        $html = (string) $this->createController()->confirm($code)->getBody();

        self::assertStringContainsString($expectedTitle, $html);
    }

    private function createController(array $overrides = []): AccountController
    {
        return $this->getTestContainer(array_merge([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            ValidatorInterface::class => $this->validator,
        ], $overrides))->get(AccountController::class);
    }
}
