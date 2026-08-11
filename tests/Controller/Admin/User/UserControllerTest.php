<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\User;

use Closure;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Admin\User\UserController;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Role;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class UserControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use UserSessionFactoryTrait;
    use ValidatorMockTrait;

    private const string USER_ROW = 'row py-2 border-bottom align-items-center';

    private SimpleAssignmentsStorage $assignmentsStorage;
    private CurrentUser $currentUser;
    private EventCaptureDispatcher $eventDispatcher;
    private FlashInterface&MockObject $flash;
    private SimpleItemsStorage $itemsStorage;
    private PasswordGeneratorInterface&MockObject $passwordGenerator;
    private PasswordHasher $passwordHasher;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->eventDispatcher = new EventCaptureDispatcher();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $this->itemsStorage = new SimpleItemsStorage();
        $this->assignmentsStorage = new SimpleAssignmentsStorage();
        $this->validator = $this->mockValidValidator();
    }

    public static function indexProvider(): iterable
    {
        yield 'clamps page beyond last to last page' => [
            [static fn(self $test): User => $test->createUser('pageuser', 'pageuser@example.com')],
            ['page' => 99],
            static function (string $html): void {
                self::assertStringContainsString('col-3 text-break">pageuser</div>', $html);
            },
        ];
        yield 'clamps per-page above maximum' => [
            [],
            ['perPage' => 500],
            static function (string $html): void {
                self::assertStringContainsString('value="100" selected', $html);
            },
        ];
        yield 'clamps per-page below minimum' => [
            [
                static fn(self $test): User => $test->createUser('user0', 'user0@example.com'),
                static fn(self $test): User => $test->createUser('user1', 'user1@example.com'),
            ],
            ['perPage' => 0],
            static function (string $html): void {
                self::assertSame(1, substr_count($html, self::USER_ROW));
                self::assertStringContainsString('page-item', $html);
            },
        ];
        yield 'custom per-page' => [
            [
                static fn(self $test): User => $test->createUser('user0', 'user0@example.com'),
                static fn(self $test): User => $test->createUser('user1', 'user1@example.com'),
                static fn(self $test): User => $test->createUser('user2', 'user2@example.com'),
            ],
            ['perPage' => 2],
            static function (string $html): void {
                self::assertSame(2, substr_count($html, self::USER_ROW));
                self::assertStringContainsString('page-item', $html);
            },
        ];
        yield 'default per-page' => [
            [],
            [],
            static function (string $html): void {
                self::assertStringContainsString('value="25" selected', $html);
            },
        ];
        yield 'filters by username' => [
            [
                static fn(self $test): User => $test->createUser('alice', 'alice@example.com'),
                static fn(self $test): User => $test->createUser('bob', 'bob@example.com'),
            ],
            ['username' => 'alice'],
            static function (string $html): void {
                self::assertStringContainsString('col-3 text-break">alice</div>', $html);
                self::assertStringNotContainsString('col-3 text-break">bob</div>', $html);
            },
        ];
        yield 'floors non-positive page to first page' => [
            [static fn(self $test): User => $test->createUser('pageuser', 'pageuser@example.com')],
            ['page' => 0],
            static function (string $html): void {
                self::assertStringContainsString('col-3 text-break">pageuser</div>', $html);
            },
        ];
        yield 'passes paginator with no results' => [
            [],
            [],
            static function (string $html): void {
                self::assertStringContainsString('Users', $html);
                self::assertSame(0, substr_count($html, self::USER_ROW));
                self::assertStringNotContainsString('page-item', $html);
            },
        ];
        yield 'shows user list' => [
            [static fn(self $test): User => $test->createUser('listeduser', 'listeduser@example.com')],
            [],
            static function (string $html): void {
                self::assertStringContainsString('Users', $html);
                self::assertStringContainsString('col-3 text-break">listeduser</div>', $html);
            },
        ];
    }

    public static function notFoundActionsProvider(): iterable
    {
        yield 'assignments' => [
            static fn(UserController $controller): string => (string) $controller->assignments(new ServerRequest('GET', '/'), 999999)->getBody(),
        ];
        yield 'show' => [
            static fn(UserController $controller): string => (string) $controller->show(999999)->getBody(),
        ];
        yield 'passwordReset' => [
            static fn(UserController $controller): string => (string) $controller->passwordReset(new ServerRequest('POST', '/'), 999999)->getBody(),
        ];
        yield 'sessions' => [
            static fn(UserController $controller): string => (string) $controller->sessions(999999)->getBody(),
        ];
        yield 'terminateSessions' => [
            static fn(UserController $controller): string => (string) $controller->terminateSessions(999999)->getBody(),
        ];
        yield 'update' => [
            static fn(UserController $controller): string => (string) $controller->update(new ServerRequest('GET', '/'), 999999)->getBody(),
        ];
        yield 'updateProfile' => [
            static fn(UserController $controller): string => (string) $controller->updateProfile(new ServerRequest('GET', '/'), 999999)->getBody(),
        ];
    }

    #[DataProvider('notFoundActionsProvider')]
    public function testActionUserNotFound(callable $invoke): void
    {
        $html = $invoke($this->createController());
        self::assertStringContainsString('User not found', $html);
    }

    public function testAssignments(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();

        // GET: shows assignments
        $html = (string) $this->createController()->assignments(new ServerRequest('GET', '/'), $userId)->getBody();
        self::assertStringContainsString('Assignments', $html);

        // POST: updates assignments
        $this->itemsStorage->add(new Role('admin'));
        $this->itemsStorage->add(new Role('editor'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['items' => ['admin', 'editor']]);
        (string) $this->createController()->assignments($request, $userId, ['admin', 'editor'])->getBody();
        $this->assertSame(['admin', 'editor'], $this->assignedNames($userId));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.assignments_update'])->all());
    }

    public function testBlock(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();

        // Nonexistent user still redirects
        $result = $this->createController()->block(new ServerRequest('POST', '/'), 999999);
        $this->assertSame(302, $result->getStatusCode());

        // Existing user: toggles block status
        $result = $this->createController()->block(new ServerRequest('POST', '/'), $userId);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertTrue(User::findById($userId)?->isBlocked());
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.block'])->all());
        $this->assertEmpty(UserAuditLog::search(['action' => 'user.unblock'])->all());
    }

    public function testConfirm(): void
    {
        // Failure: already confirmed user shows error
        $user = $this->createUser(username: 'confirmed_user', email: 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();
        $html = (string) $this->createController()->confirm(new ServerRequest('POST', '/'), (int) $user->getId())->getBody();
        self::assertStringContainsString('Unable to confirm', $html);

        // Success: unconfirmed user gets confirmed and redirects
        $unconfirmed = $this->createUser(username: 'unconfirmed_user', email: 'unconfirmed@example.com');
        $userId = (int) $unconfirmed->getId();
        $result = $this->createController()->confirm(new ServerRequest('POST', '/'), $userId);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertNotNull(User::findById($userId)?->getConfirmedAt());
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.confirm'])->all());
    }

    public function testCreate(): void
    {
        // GET: shows create form
        $html = (string) $this->createController()->create(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Create user', $html);

        // POST success: creates user with auto-generated password
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'newuser', 'email' => 'new@example.com', 'password' => '', 'passwordRepeat' => '']]);
        $this->passwordGenerator->expects($this->once())->method('generate')->with(12)->willReturn('autogenerated123');
        $result = $this->createController()->create($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('admin-users', $result->getHeaderLine('Location'));
        $this->assertNotNull(User::findByEmail('new@example.com'));

        // POST with assigned items: assigns role to newly created user
        $this->itemsStorage->add(new Role('admin'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'register' => ['username' => 'withitem', 'email' => 'withitem@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123'],
            'assignedItems' => ['admin'],
        ]);
        $result = $this->createController()->create($request, ['admin']);
        $this->assertSame(302, $result->getStatusCode());
        $created = User::findByUsername('withitem');
        $this->assertNotNull($created);
        $this->assertSame(['admin'], $this->assignedNames((int) $created->getId()));

        // POST service failure: re-renders form with existing email
        $this->createUser('existing', 'existing@example.com');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'different', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);
        $html = (string) $this->createController()->create($request)->getBody();
        self::assertStringContainsString('Create user', $html);
    }

    public function testDelete(): void
    {
        // Delete different user: succeeds and redirects
        $admin1 = $this->createUser(username: 'delete_admin1', email: 'delete_admin1@example.com');
        $this->currentUser->login($admin1);
        $user = $this->createUser(username: 'delete_target', email: 'delete_target@example.com');
        $userId = (int) $user->getId();
        $result = $this->createController()->delete(new ServerRequest('POST', '/'), $userId);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('admin-users', $result->getHeaderLine('Location'));
        $this->assertNull(User::findById($userId));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.delete'])->all());
        $deleteEvent = $this->eventDispatcher->getEvent(UserEvent::class);
        $this->assertInstanceOf(UserEvent::class, $deleteEvent);
        $this->assertSame(UserEvent::DELETE, $deleteEvent->getType());

        // Delete own user: shows error
        $admin2 = $this->createUser(username: 'delete_admin2', email: 'delete_admin2@example.com');
        $this->currentUser->login($admin2);
        $html = (string) $this->createController()->delete(new ServerRequest('POST', '/'), (int) $admin2->getId())->getBody();
        self::assertStringContainsString('cannot delete', strtolower($html));

        // Nonexistent user: shows "not found" error
        $html = (string) $this->createController()->delete(new ServerRequest('POST', '/'), 999999)->getBody();
        self::assertStringContainsString('User not found', $html);
    }

    public function testForcePasswordChange(): void
    {
        // Failure: nonexistent user shows error
        $html = (string) $this->createController()->forcePasswordChange(new ServerRequest('POST', '/'), 999999)->getBody();
        self::assertStringContainsString('There was an error', $html);

        // Success: resets password-changed timestamp
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();
        $result = $this->createController()->forcePasswordChange(new ServerRequest('POST', '/'), $userId);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame(0, User::findById($userId)?->getPasswordChangedAt());
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.force_password_change'])->all());
    }

    #[DataProvider('indexProvider')]
    public function testIndex(array $setup, array $indexArgs, Closure $assertHtml): void
    {
        foreach ($setup as $setupUser) {
            $setupUser($this);
        }

        $html = (string) $this->createController()->index(...$indexArgs)->getBody();

        $assertHtml($html);
    }

    public function testInfo(): void
    {
        // Shows user with profile
        $user = $this->createUserWithProfile();
        $profile = $user->getProfile();
        $this->assertNotNull($profile);
        $profile->setTimezone('America/New_York');
        $profile->save();

        $admin = $this->loginAdmin();
        $adminProfile = new UserProfile();
        $adminProfile->setUserId((int) $admin->getId());
        $adminProfile->setTimezone('Asia/Tokyo');
        $adminProfile->save();

        $html = (string) $this->createController()->show((int) $user->getId())->getBody();
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized($user->getCreatedAt(), $this->createTranslator()->getLocale(), 'Asia/Tokyo'),
            $html,
        );

        // Shows user without profile
        $noProfileUser = $this->createUser(username: 'noprofileuser');
        $html = (string) $this->createController()->show((int) $noProfileUser->getId())->getBody();
        self::assertStringContainsString('noprofileuser', $html);
    }

    public function testPasswordReset(): void
    {
        // Success: sends recovery message and issues token
        $user = $this->createUser(email: 'test@example.com');
        $userId = (int) $user->getId();
        $html = (string) $this->createController()->passwordReset(new ServerRequest('POST', '/'), $userId)->getBody();
        self::assertStringContainsString('Recovery message sent', $html);
        $this->assertNotEmpty(UserToken::findByUserId($userId));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.password_reset_triggered'])->all());
    }

    public function testSessions(): void
    {
        // Renders when viewer has no profile
        $user = $this->createUser(username: 'sess_user1', email: 'sessuser1@example.com');
        $this->createUserSession((int) $user->getId(), 'sess-x');
        $admin1 = $this->createUser(username: 'sess_admin1', email: 'sessadmin1@example.com');
        $this->currentUser->login($admin1);
        $html = (string) $this->createController()->sessions((int) $user->getId())->getBody();
        self::assertStringContainsString('Session management', $html);

        // Shows sessions with timezone formatting
        $targetUser = $this->createUser(username: 'sess_target', email: 'sesstarget@example.com');
        $targetProfile = new UserProfile();
        $targetProfile->setUserId((int) $targetUser->getId());
        $targetProfile->setTimezone('America/New_York');
        $targetProfile->save();

        $updatedAt = time();
        $session = new UserSessions();
        $session->setUserId((int) $targetUser->getId());
        $session->setSessionId('abc');
        $session->setIp('203.0.113.1');
        $session->setCreatedAt($updatedAt);
        $session->setUpdatedAt($updatedAt);
        $session->save();

        $admin2 = $this->createUser(username: 'sess_admin2', email: 'sessadmin2@example.com');
        $this->currentUser->login($admin2);
        $adminProfile = new UserProfile();
        $adminProfile->setUserId((int) $admin2->getId());
        $adminProfile->setTimezone('Asia/Tokyo');
        $adminProfile->save();

        $html = (string) $this->createController()->sessions((int) $targetUser->getId())->getBody();
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized($updatedAt, $this->createTranslator()->getLocale(), 'Asia/Tokyo'),
            $html,
        );
    }

    public function testSwitchIdentity(): void
    {
        // Failure: nonexistent user shows error
        $html = (string) $this->createController()->switchIdentity(new ServerRequest('POST', '/'), 999999)->getBody();
        self::assertStringContainsString('User not found', $html);

        // Success: switches and redirects
        $target = $this->createUser(username: 'switchtarget', email: 'switchtarget@example.com');
        $this->flash->expects($this->once())->method('add')->with('toast.success');
        $result = $this->createController()->switchIdentity(new ServerRequest('POST', '/'), (int) $target->getId());
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/user', $result->getHeaderLine('Location'));
    }

    public function testSwitchIdentityLogsOriginalActor(): void
    {
        $admin = $this->createUser(username: 'realadmin', email: 'realadmin@example.com');
        $targetUser = $this->createUser(username: 'targetuser', email: 'targetuser@example.com');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $currentUser = new CurrentUser($this->createMock(IdentityRepositoryInterface::class), $eventDispatcher);
        $currentUser->login($admin);
        $controller = $this->createController(overrides: [CurrentUser::class => $currentUser]);

        $controller->switchIdentity(new ServerRequest('POST', '/'), (int) $targetUser->getId());

        $logs = UserAuditLog::search(['action' => 'user.switch_identity'])->all();
        self::assertCount(1, $logs);
        self::assertSame((int) $admin->getId(), $logs[0]->getActorUserId());
        self::assertSame((int) $targetUser->getId(), $logs[0]->getTargetUserId());
    }

    public function testSwitchIdentityRestore(): void
    {
        // Failure: no original identity in session shows error
        $html = (string) $this->createController()->switchIdentityRestore(new ServerRequest('POST', '/'))->getBody();
        self::assertStringContainsString('No original identity to restore', $html);

        // Success: restores and redirects
        $original = $this->createUser(username: 'original', email: 'original@example.com');
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', (string) $original->getId());
        $this->flash->expects($this->once())->method('add')->with('toast.success');
        $result = $this->createController(overrides: [SessionInterface::class => $session])
            ->switchIdentityRestore(new ServerRequest('POST', '/'));
        $this->assertSame(302, $result->getStatusCode());
        $this->assertFalse($session->has('voyti_original_admin_user'));
    }

    public function testTerminateSessions(): void
    {
        // Does not overwrite already-revoked timestamp
        $user1 = $this->createUser(username: 'term_user1', email: 'termuser1@example.com');
        $userId = (int) $user1->getId();
        $session = $this->createUserSession($userId, 'sess-1');
        $session->setRevokedAt(1000);
        $session->save();
        $this->createController()->terminateSessions($userId);
        $refreshed = UserSessions::findByUserIdAndSessionId($userId, 'sess-1');
        $this->assertNotNull($refreshed);
        $this->assertSame(1000, $refreshed->getRevokedAt());

        // Success: revokes sessions and redirects
        $targetUser = $this->createUser(username: 'term_target', email: 'termtarget@example.com');
        $targetId = (int) $targetUser->getId();
        $this->createUserSession($targetId, 'sess-1');
        $result = $this->createController()->terminateSessions($targetId);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('id=' . $targetId, $result->getHeaderLine('Location'));
        $sessions = UserSessions::findByUserId($targetId);
        $this->assertCount(1, $sessions);
        $this->assertTrue($sessions[0]->isRevoked());
    }

    public function testUpdate(): void
    {
        // GET: shows update form
        $user = $this->createUser(username: 'edituser', email: 'edituser@example.com');
        $html = (string) $this->createController()->update(new ServerRequest('GET', '/'), (int) $user->getId())->getBody();
        self::assertStringContainsString('Update user: edituser', $html);

        // POST success: updates user
        $userId = (int) $user->getId();
        $user->setUpdatedAt(1000);
        $user->save();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated1', 'email' => 'updated1@example.com', 'password' => ''], 'assignedItems' => []]);
        $result = $this->createController()->update($request, $userId);
        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('updated1', $updated->getUsername());
        $this->assertSame('updated1@example.com', $updated->getEmail());
        $this->assertNotSame(1000, $updated->getUpdatedAt());
        $logs = UserAuditLog::search(['action' => 'user.update'])->all();
        self::assertNotEmpty($logs);
        self::assertStringContainsString('"passwordChanged":false', (string) $logs[0]->getContext());

        // POST with assigned items: assigns role
        $roleUser = $this->createUser(username: 'roleuser', email: 'roleuser@example.com');
        $roleUserId = (int) $roleUser->getId();
        $this->itemsStorage->add(new Role('admin'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated2', 'email' => 'updated2@example.com', 'password' => ''], 'assignedItems' => ['admin']]);
        $result = $this->createController()->update($request, $roleUserId, ['admin']);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame(['admin'], $this->assignedNames($roleUserId));

        // POST with password change: updates hash and timestamp
        $passUser = $this->createUser(username: 'passuser', email: 'passuser@example.com');
        $passUserId = (int) $passUser->getId();
        $originalHash = $passUser->getPasswordHash();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated3', 'email' => 'updated3@example.com', 'password' => 'newpass'], 'assignedItems' => []]);
        $result = $this->createController()->update($request, $passUserId);
        $this->assertSame(302, $result->getStatusCode());
        $passUpdated = User::findById($passUserId);
        $this->assertNotNull($passUpdated);
        $this->assertNotSame($originalHash, $passUpdated->getPasswordHash());
        $this->assertNotNull($passUpdated->getPasswordChangedAt());
        $passLogs = UserAuditLog::search(['action' => 'user.update', 'target_user_id' => $passUserId])->all();
        self::assertNotEmpty($passLogs);
        self::assertStringContainsString('"passwordChanged":true', (string) $passLogs[0]->getContext());

        // POST with reused password: shows error and leaves account unchanged
        $oldPassUser = new User();
        $oldPassUser->setUsername('oldpassuser');
        $oldPassUser->setEmail('oldpass@example.com');
        $oldPassUser->setPasswordHash($this->passwordHasher->hash('originalpass'));
        $oldPassUser->setAuthKey('key');
        $oldPassUser->setCreatedAt(time());
        $oldPassUser->setUpdatedAt(time());
        $oldPassUser->save();
        $oldPassUserId = (int) $oldPassUser->getId();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated4', 'email' => 'updated4@example.com', 'password' => 'originalpass'], 'assignedItems' => []]);
        $html = (string) $this->createController(VoytiConfigFactory::create(maxPasswordAge: 90))->update($request, $oldPassUserId)->getBody();
        self::assertStringContainsString('Update user: oldpassuser', $html);
        self::assertStringContainsString('This password has been used recently.', $html);
        $oldPassUpdated = User::findById($oldPassUserId);
        $this->assertNotNull($oldPassUpdated);
        $this->assertSame('oldpassuser', $oldPassUpdated->getUsername());
    }

    public function testUpdateProfile(): void
    {
        // GET: creates new profile when none exists
        $user = $this->createUser(email: 'testuser@example.com');
        $html = (string) $this->createController()->updateProfile(new ServerRequest('GET', '/'), (int) $user->getId())->getBody();
        self::assertStringContainsString('Update profile', $html);

        // POST: creates profile bound to user and redirects
        $userId = (int) $user->getId();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'Updated', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '1990-05-15']]);
        $result = $this->createController()->updateProfile($request, $userId);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('id=' . $userId, $result->getHeaderLine('Location'));
        $updated = UserProfile::findByUserId($userId);
        $this->assertNotNull($updated);
        $this->assertSame($userId, $updated->getUserId());
        $this->assertSame('Updated', $updated->getName());
        $this->assertSame('1990-05-15', $updated->getBirthday()?->format('Y-m-d'));
    }

    private function assignedNames(int $userId): array
    {
        return array_values(array_map(
            static fn(Assignment $a): string => $a->getItemName(),
            $this->assignmentsStorage->getByUserId((string) $userId),
        ));
    }

    private function createController(?VoytiConfig $config = null, array $overrides = []): UserController
    {
        $definitions = [
            AssignmentsStorageInterface::class => $this->assignmentsStorage,
            CurrentUser::class => $this->currentUser,
            EventDispatcherInterface::class => $this->eventDispatcher,
            FlashNotifier::class => new FlashNotifier($this->flash),
            ItemsStorageInterface::class => $this->itemsStorage,
            PasswordGeneratorInterface::class => $this->passwordGenerator,
            ValidatorInterface::class => $this->validator,
            ...$overrides,
        ];

        if ($config !== null) {
            $definitions[VoytiConfig::class] = $config;
        }

        return $this->getTestContainer($definitions)->get(UserController::class);
    }

    private function createUserWithProfile(string $name = 'John'): User
    {
        $user = $this->createUser(email: 'testuser@example.com');

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName($name);
        $profile->save();

        return $user;
    }

    private function loginAdmin(): User
    {
        $admin = $this->createUser(username: 'adminuser', email: 'adminuser@example.com');
        $this->currentUser->login($admin);

        return $admin;
    }
}
