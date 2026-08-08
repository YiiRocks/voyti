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
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
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
    private FlashInterface&MockObject $flash;
    private SimpleItemsStorage $itemsStorage;
    private PasswordGeneratorInterface&MockObject $passwordGenerator;
    private PasswordHasher $passwordHasher;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
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

    public function testAssignmentsGetShowsAssignments(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');

        $html = (string) $this->createController()->assignments(new ServerRequest('GET', '/'), (int) $user->getId())->getBody();

        self::assertStringContainsString('Assignments', $html);
    }

    public function testAssignmentsPostUpdates(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();
        $this->itemsStorage->add(new Role('admin'));
        $this->itemsStorage->add(new Role('editor'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['items' => ['admin', 'editor']]);

        (string) $this->createController()->assignments($request, $userId, ['admin', 'editor'])->getBody();

        // The real UpdateAssignmentsService persisted both roles for the user.
        $this->assertSame(['admin', 'editor'], $this->assignedNames($userId));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.assignments_update'])->all());
    }

    public function testAssignmentsUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): string => (string) $controller->assignments(new ServerRequest('GET', '/'), 999999)->getBody(),
        );
    }

    public function testBlockNonExistentUserStillRedirects(): void
    {
        $result = $this->createController()->block(new ServerRequest('POST', '/'), 999999);

        $this->assertSame(302, $result->getStatusCode());
    }

    public function testBlockTogglesUserBlock(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();

        $result = $this->createController()->block(new ServerRequest('POST', '/'), $userId);

        $this->assertSame(302, $result->getStatusCode());
        // The real BlockService flipped the account to blocked.
        $this->assertTrue(User::findById($userId)?->isBlocked());
        // Blocking a previously-unblocked user records the "block" (not "unblock") audit action.
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.block'])->all());
        $this->assertEmpty(UserAuditLog::search(['action' => 'user.unblock'])->all());
    }

    public function testConfirmFailureShowsError(): void
    {
        // An already-confirmed user makes the real ConfirmationService return false.
        $user = $this->createUser(email: 'testuser@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        $html = (string) $this->createController()->confirm(new ServerRequest('POST', '/'), (int) $user->getId())->getBody();

        self::assertStringContainsString('Unable to confirm', $html);
    }

    public function testConfirmSuccessful(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();

        $result = $this->createController()->confirm(new ServerRequest('POST', '/'), $userId);

        $this->assertSame(302, $result->getStatusCode());
        // The real ConfirmationService marked the account confirmed.
        $this->assertNotNull(User::findById($userId)?->getConfirmedAt());
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.confirm'])->all());
    }

    public function testCreateGetShowsForm(): void
    {
        $html = (string) $this->createController()->create(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Create user', $html);
    }

    public function testCreatePostSuccessful(): void
    {
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'newuser', 'email' => 'new@example.com', 'password' => '', 'passwordRepeat' => '']]);

        // An empty password must trigger generation of a 12-character password.
        $this->passwordGenerator->expects($this->once())->method('generate')->with(12)->willReturn('autogenerated123');

        $result = $this->createController()->create($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('admin-users', $result->getHeaderLine('Location'));
        // The real CreateService created the account with the generated password.
        $this->assertNotNull(User::findByEmail('new@example.com'));
    }

    public function testCreatePostWithAssignedItemsAssignsUser(): void
    {
        $this->itemsStorage->add(new Role('admin'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'register' => ['username' => 'newuser', 'email' => 'new@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123'],
            'assignedItems' => ['admin'],
        ]);

        $result = $this->createController()->create($request, ['admin']);

        $this->assertSame(302, $result->getStatusCode());
        $createdUser = User::findByUsername('newuser');
        $this->assertNotNull($createdUser);
        // The real UpdateAssignmentsService assigned the role to the newly created user.
        $this->assertSame(['admin'], $this->assignedNames((int) $createdUser->getId()));
    }

    public function testCreatePostWithServiceFailure(): void
    {
        // A user with this email already exists, so the real CreateService reports a uniqueness conflict.
        $this->createUser('existing', 'existing@example.com');

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'existing2', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);

        // On failure the create form is re-rendered rather than redirecting.
        $html = (string) $this->createController()->create($request)->getBody();

        self::assertStringContainsString('Create user', $html);
    }

    public function testDeleteDifferentUser(): void
    {
        $this->loginAdmin();

        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();

        $result = $this->createController()->delete(new ServerRequest('POST', '/'), $userId);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('admin-users', $result->getHeaderLine('Location'));
        $this->assertNull(User::findById($userId));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.delete'])->all());
    }

    public function testDeleteNonExistentUserShowsError(): void
    {
        $this->loginAdmin();

        $html = (string) $this->createController()->delete(new ServerRequest('POST', '/'), 999999)->getBody();

        self::assertStringContainsString('User not found', $html);
    }

    public function testDeleteOwnUserShowsError(): void
    {
        $admin = $this->loginAdmin();

        $html = (string) $this->createController()->delete(new ServerRequest('POST', '/'), (int) $admin->getId())->getBody();

        self::assertStringContainsString('cannot delete', strtolower($html));
    }

    public function testForcePasswordChangeFailsShowsError(): void
    {
        $html = (string) $this->createController()->forcePasswordChange(new ServerRequest('POST', '/'), 999999)->getBody();

        self::assertStringContainsString('There was an error', $html);
    }

    public function testForcePasswordChangeUserFound(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();

        $result = $this->createController()->forcePasswordChange(new ServerRequest('POST', '/'), $userId);

        $this->assertSame(302, $result->getStatusCode());
        // The real ExpireService reset the password-changed timestamp to force a change.
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

    public function testInfoShowsUserInfo(): void
    {
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

        // The registration date is formatted in the viewing admin's timezone.
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized($user->getCreatedAt(), $this->createTranslator()->getLocale(), 'Asia/Tokyo'),
            $html,
        );
    }

    public function testInfoShowsUserInfoWhenUserHasNoProfile(): void
    {
        $user = $this->createUser(username: 'noprofileuser');
        $this->loginAdmin();

        $html = (string) $this->createController()->show((int) $user->getId())->getBody();

        // The info page renders using the username as its heading.
        self::assertStringContainsString('noprofileuser', $html);
    }

    public function testInfoUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): string => (string) $controller->show(999999)->getBody(),
        );
    }

    public function testPasswordResetUserFound(): void
    {
        $user = $this->createUser(email: 'test@example.com');
        $userId = (int) $user->getId();

        $html = (string) $this->createController()->passwordReset(new ServerRequest('POST', '/'), $userId)->getBody();

        // The recovery result message is rendered, and the real RecoveryService issued a token.
        self::assertStringContainsString('Recovery message sent', $html);
        $this->assertNotEmpty(UserToken::findByUserId($userId));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'user.password_reset_triggered'])->all());
    }

    public function testPasswordResetUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): string => (string) $controller->passwordReset(new ServerRequest('POST', '/'), 999999)->getBody(),
        );
    }

    public function testSessionsRendersWhenViewerHasNoProfile(): void
    {
        $user = $this->createUser(email: 'targetuser@example.com');
        $this->createUserSession((int) $user->getId(), 'sess-x');

        // The viewing admin has no profile, so viewer-timezone resolution must handle a null profile.
        $this->loginAdmin();

        $html = (string) $this->createController()->sessions((int) $user->getId())->getBody();

        self::assertStringContainsString('Session management', $html);
    }

    public function testSessionsUserFound(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $targetProfile = new UserProfile();
        $targetProfile->setUserId((int) $user->getId());
        $targetProfile->setTimezone('America/New_York');
        $targetProfile->save();

        $updatedAt = time();
        $session = new UserSessions();
        $session->setUserId((int) $user->getId());
        $session->setSessionId('abc');
        $session->setIp('203.0.113.1');
        $session->setCreatedAt($updatedAt);
        $session->setUpdatedAt($updatedAt);
        $session->save();

        $admin = $this->loginAdmin();
        $adminProfile = new UserProfile();
        $adminProfile->setUserId((int) $admin->getId());
        $adminProfile->setTimezone('Asia/Tokyo');
        $adminProfile->save();

        $html = (string) $this->createController()->sessions((int) $user->getId())->getBody();

        // The session's last-seen time is formatted in the viewing admin's timezone.
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized($updatedAt, $this->createTranslator()->getLocale(), 'Asia/Tokyo'),
            $html,
        );
    }

    public function testSessionsUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): string => (string) $controller->sessions(999999)->getBody(),
        );
    }

    public function testSwitchIdentityFailureShowsError(): void
    {
        // No user with this id exists, so the real SwitchIdentityService fails.
        $html = (string) $this->createController()->switchIdentity(new ServerRequest('POST', '/'), 999999)->getBody();

        self::assertStringContainsString('User not found', $html);
    }

    public function testSwitchIdentityLogsOriginalActorNotTarget(): void
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

    public function testSwitchIdentityRestoreFailureShowsError(): void
    {
        // No original identity is stored in the session, so the real restore fails.
        $html = (string) $this->createController()->switchIdentityRestore(new ServerRequest('POST', '/'))->getBody();

        self::assertStringContainsString('No original identity to restore', $html);
    }

    public function testSwitchIdentityRestoreSuccessRedirects(): void
    {
        $original = $this->createUser(username: 'original', email: 'original@example.com');
        $session = new FakeSession();
        $session->set('voyti_original_admin_user', (string) $original->getId());

        // A success flash is set before redirecting.
        $this->flash->expects($this->once())->method('set');

        $result = $this->createController(overrides: [SessionInterface::class => $session])
            ->switchIdentityRestore(new ServerRequest('POST', '/'));

        $this->assertSame(302, $result->getStatusCode());
        // The real SwitchIdentityService cleared the stored original identity.
        $this->assertFalse($session->has('voyti_original_admin_user'));
    }

    public function testSwitchIdentitySuccessRedirects(): void
    {
        $target = $this->createUser(username: 'switchtarget', email: 'switchtarget@example.com');

        // A success flash is set before redirecting.
        $this->flash->expects($this->once())->method('set');

        $result = $this->createController()->switchIdentity(new ServerRequest('POST', '/'), (int) $target->getId());

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/user', $result->getHeaderLine('Location'));
    }

    public function testTerminateSessionsDoesNotOverwriteAlreadyRevokedTimestamp(): void
    {
        $user = $this->createUser(email: 'testuser2@example.com');
        $userId = (int) $user->getId();
        $session = $this->createUserSession($userId, 'sess-1');
        $session->setRevokedAt(1000);
        $session->save();

        $this->createController()->terminateSessions($userId);

        $refreshed = UserSessions::findByUserIdAndSessionId($userId, 'sess-1');
        $this->assertNotNull($refreshed);
        $this->assertSame(1000, $refreshed->getRevokedAt());
    }

    public function testTerminateSessionsUserFound(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'sess-1');

        $result = $this->createController()->terminateSessions($userId);

        $this->assertSame(302, $result->getStatusCode());
        // The redirect targets this user's sessions page (id carried in the URL).
        $this->assertStringContainsString('id=' . $userId, $result->getHeaderLine('Location'));
        $sessions = UserSessions::findByUserId($userId);
        $this->assertCount(1, $sessions);
        $this->assertTrue($sessions[0]->isRevoked());
    }

    public function testTerminateSessionsUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): string => (string) $controller->terminateSessions(999999)->getBody(),
        );
    }

    public function testUpdateGetShowsForm(): void
    {
        $user = $this->createUser(username: 'edituser', email: 'testuser@example.com');

        $html = (string) $this->createController()->update(new ServerRequest('GET', '/'), (int) $user->getId())->getBody();

        self::assertStringContainsString('Update user: edituser', $html);
    }

    public function testUpdatePostSuccessful(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();
        // A distinct past timestamp so the update's setUpdatedAt(time()) bump is observable.
        $user->setUpdatedAt(1000);
        $user->save();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => ''], 'assignedItems' => []]);

        $result = $this->createController()->update($request, $userId);

        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('updated', $updated->getUsername());
        $this->assertSame('updated@example.com', $updated->getEmail());
        $this->assertNotSame(1000, $updated->getUpdatedAt());
        // The update is audited, recording that the password was not changed.
        $logs = UserAuditLog::search(['action' => 'user.update'])->all();
        self::assertNotEmpty($logs);
        self::assertStringContainsString('"passwordChanged":false', (string) $logs[0]->getContext());
    }

    public function testUpdatePostWithAssignedItemsAssignsUser(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();
        $this->itemsStorage->add(new Role('admin'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => ''], 'assignedItems' => ['admin']]);

        $result = $this->createController()->update($request, $userId, ['admin']);

        $this->assertSame(302, $result->getStatusCode());
        // The real UpdateAssignmentsService assigned the role during the update.
        $this->assertSame(['admin'], $this->assignedNames($userId));
    }

    public function testUpdatePostWithPasswordChange(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();
        $originalHash = $user->getPasswordHash();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => 'newpass'], 'assignedItems' => []]);

        $result = $this->createController()->update($request, $userId);

        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertNotSame($originalHash, $updated->getPasswordHash());
        $this->assertNotNull($updated->getPasswordChangedAt());
        // The update audit records that the password was changed.
        $logs = UserAuditLog::search(['action' => 'user.update'])->all();
        self::assertNotEmpty($logs);
        self::assertStringContainsString('"passwordChanged":true', (string) $logs[0]->getContext());
    }

    public function testUpdatePostWithPreviouslyUsedPasswordShowsError(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('testuser@example.com');
        $user->setPasswordHash($this->passwordHasher->hash('originalpass'));
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        $userId = (int) $user->getId();

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => 'originalpass'], 'assignedItems' => []]);

        $html = (string) $this->createController(VoytiConfigFactory::create(maxPasswordAge: 90))->update($request, $userId)->getBody();

        // The reused password re-renders the account form with the error and leaves the account unchanged.
        self::assertStringContainsString('Update user: testuser', $html);
        self::assertStringContainsString('This password has been used recently.', $html);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('testuser', $updated->getUsername());
    }

    public function testUpdateProfileGetCreatesNewProfileWhenNoneExists(): void
    {
        $user = $this->createUser(email: 'testuser@example.com');

        $html = (string) $this->createController()->updateProfile(new ServerRequest('GET', '/'), (int) $user->getId())->getBody();

        self::assertStringContainsString('Update profile', $html);
    }

    public function testUpdateProfilePostSuccessful(): void
    {
        // No pre-existing profile, so the controller creates one bound to this user id.
        $user = $this->createUser(email: 'testuser@example.com');
        $userId = (int) $user->getId();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'Updated', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '1990-05-15']]);

        $result = $this->createController()->updateProfile($request, $userId);

        $this->assertSame(302, $result->getStatusCode());
        // The redirect returns to this user's profile page (id carried in the URL).
        $this->assertStringContainsString('id=' . $userId, $result->getHeaderLine('Location'));
        $updated = UserProfile::findByUserId($userId);
        $this->assertNotNull($updated);
        $this->assertSame($userId, $updated->getUserId());
        $this->assertSame('Updated', $updated->getName());
        $this->assertSame('1990-05-15', $updated->getBirthday()?->format('Y-m-d'));
    }

    public function testUpdateProfileUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): string => (string) $controller->updateProfile(new ServerRequest('GET', '/'), 999999)->getBody(),
        );
    }

    public function testUpdateUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): string => (string) $controller->update(new ServerRequest('GET', '/'), 999999)->getBody(),
        );
    }

    private function assertNotFoundRendersError(callable $invoke): void
    {
        $html = $invoke($this->createController());

        self::assertStringContainsString('User not found', $html);
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
            FlashInterface::class => $this->flash,
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
