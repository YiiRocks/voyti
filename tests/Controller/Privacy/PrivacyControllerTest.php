<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Privacy;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Privacy\PrivacyController;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class PrivacyControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private CurrentUser $currentUser;
    private EventCaptureDispatcher $eventDispatcher;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->eventDispatcher = new EventCaptureDispatcher();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
    }

    public static function accountDeleteToggleProvider(): iterable
    {
        yield 'enabled shows delete link' => [true, true];
        yield 'disabled omits delete link' => [false, false];
    }

    public function testDeleteGetShowsForm(): void
    {
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->delete(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Delete my account', $html);
    }

    public function testDeletePostWithCorrectPasswordRemovesAccountAndTerminatesSessions(): void
    {
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $userId = $user->getIdOrZero();
        $this->currentUser->login($user);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => 'secret']]);

        $html = (string) $this->createController()->delete($request)->getBody();

        self::assertStringContainsString('Your account has been deleted', $html);
        self::assertNull(User::findById($userId));

        $event = $this->eventDispatcher->getEvent(UserEvent::class);
        self::assertInstanceOf(UserEvent::class, $event);
        self::assertSame(UserEvent::DELETE, $event->getType());

        // Session termination is dispatched as a distinct event from account deletion, proving
        // TerminateUserSessionsService::run() was actually invoked rather than skipped.
        $sessionEvent = $this->eventDispatcher->getEvent(SessionEvent::class);
        self::assertInstanceOf(SessionEvent::class, $sessionEvent);
        self::assertSame(SessionEvent::SESSION_TERMINATED, $sessionEvent->getData()['type'] ?? null);
    }

    public function testDeletePostWithWrongPasswordShowsFormAgain(): void
    {
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $userId = $user->getIdOrZero();
        $this->currentUser->login($user);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => 'wrong']]);

        $html = (string) $this->createController()->delete($request)->getBody();

        self::assertStringContainsString('Delete my account', $html);
        self::assertStringContainsString('Incorrect password', $html);
        self::assertNotNull(User::findById($userId));
        self::assertFalse($this->eventDispatcher->hasEvent(UserEvent::class));
    }

    #[DataProvider('accountDeleteToggleProvider')]
    public function testIndexAccountDeleteLink(bool $allowAccountDelete, bool $expectDeleteLink): void
    {
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController([
            VoytiConfig::class => VoytiConfigFactory::create(allowAccountDelete: $allowAccountDelete),
        ])->index()->getBody();

        if ($expectDeleteLink) {
            self::assertStringContainsString('Delete my account', $html);
            self::assertStringContainsString('user-privacy-delete', $html);
        } else {
            self::assertStringNotContainsString('Delete my account', $html);
        }
    }

    public function testIndexShowsPrivacyLinksContributedByPackages(): void
    {
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        // An unrecognized translation key falls back to the raw key string, which is enough to
        // prove the contributed link (label + generated URL) actually made it into the rendered
        // page without colliding with any of the real menu/label text already on it.
        $html = (string) $this->createController([
            VoytiConfig::class => VoytiConfigFactory::create(
                allowAccountDelete: false,
                privacyMenuItems: [
                    ['label' => 'test.privacy.contributed.link', 'category' => 'voyti', 'route' => 'voyti/user'],
                ],
            ),
        ])->index()->getBody();

        self::assertStringContainsString('test.privacy.contributed.link', $html);
    }

    private function createController(array $overrides = []): PrivacyController
    {
        return $this->getTestContainer(array_merge([
            CurrentUser::class => $this->currentUser,
            EventDispatcherInterface::class => $this->eventDispatcher,
            FlashInterface::class => $this->flash,
        ], $overrides))->get(PrivacyController::class);
    }
}
