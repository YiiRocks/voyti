<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Targeted mutation-testing coverage for {@see \YiiRocks\Voyti\Controller\SettingsController}.
 */
final class SettingsControllerTest extends TestCase
{
    private ?ConnectionInterface $db = null;
    private ControllerHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getDb();
        ConnectionProvider::set($this->db);
        $this->createSchema($this->db);

        $this->harness = new ControllerHarness(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->dropSchema($this->db);
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testAccountActionInvalidSubmissionDisplaysValidationErrors(): void
    {
        // The shared harness wires a fake validator that always reports "valid", so
        // the invalid-submission branch (and processValidationResult's effect on it)
        // can only be observed with a controller built against a real validator.
        $user = $this->createUser('leon', 'leon@example.test', 'old-password');
        $controller = $this->buildSettingsControllerWithRealValidator();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $controller->account(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new SettingsForm($this->harness->translator),
                    [
                        'username' => 'leon',
                        'email' => 'leon@example.test',
                        'password' => 'abc',
                    ],
                ),
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('Your account details have been updated', $html);
        $this->assertStringContainsString('must contain at least 6 characters', $html);

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue((new PasswordHasher())->validate('old-password', $reloaded->getPasswordHash()));
    }

    public function testAccountActionKillsPasswordAndPersistenceMutants(): void
    {
        $user = $this->createUser('liam', 'liam@example.test', 'old-password');
        $userId = (int) $user->getId();
        $user->setUpdatedAt(time() - 1000);
        $user->save();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->account(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new SettingsForm($this->harness->translator),
                    [
                        'username' => 'liam',
                        'email' => 'liam@example.test',
                        'password' => 'new-password-123',
                    ],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Your account details have been updated');

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue((new PasswordHasher())->validate('new-password-123', $reloaded->getPasswordHash()));
        $this->assertNotNull($reloaded->getPasswordChangedAt());
        $this->assertGreaterThan(time() - 1000, $reloaded->getUpdatedAt());
    }

    public function testAccountActionUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->account(
            $this->harness->request(Method::GET),
        );

        $this->assertResponseContains($response, 'User not found');
        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('ghost-negative', $html);
        $this->assertStringNotContainsString('ghost-one', $html);
    }

    public function testConfirmActionUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->confirm(
            $this->harness->request(Method::GET),
            'irrelevant-code',
        );

        $this->assertResponseContains($response, 'User not found');
    }

    public function testDeleteActionDispatchesUserEventTwiceAndDeletesUser(): void
    {
        $user = $this->createUser('dana', 'dana@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->delete(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'Your account has been deleted');
        $this->assertNull($this->harness->users->findById($userId));

        $userEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof UserEvent,
        ));
        $this->assertCount(2, $userEvents);
    }

    public function testDeleteActionUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->delete(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'Your account has been deleted');
        $this->assertSame([], $this->harness->eventDispatcher->events());
        $this->assertNotNull($this->harness->users->findById(-1));
        $this->assertNotNull($this->harness->users->findById(1));
    }

    public function testGdprConsentGetReflectsExistingConsentForRealUser(): void
    {
        $user = $this->createUser('erin', 'erin@example.test');
        $user->setGdprConsent(true);
        $user->save();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->gdprConsent(
            $this->harness->request(Method::GET),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('checked type="checkbox"', $html);
    }

    public function testGdprConsentGetUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->insertGhostUser(-1, 'ghost-negative', gdprConsent: true);
        $this->insertGhostUser(1, 'ghost-one', gdprConsent: true);
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->gdprConsent(
            $this->harness->request(Method::GET),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('checked type="checkbox"', $html);
    }

    public function testGdprConsentPostPersistsConsentForRealUser(): void
    {
        $user = $this->createUser('frank', 'frank@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->gdprConsent(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprConsentForm($this->harness->translator),
                    ['consent' => true],
                ),
            ),
        );

        $this->assertResponseContains($response, 'GDPR consent has been saved');

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue($reloaded->isGdprConsent());
        $this->assertNotNull($reloaded->getGdprConsentDate());
    }

    public function testGdprConsentPostUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->gdprConsent(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprConsentForm($this->harness->translator),
                    ['consent' => true],
                ),
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('GDPR consent has been saved', $html);
        $this->assertFalse($this->harness->users->findById(-1)->isGdprConsent());
        $this->assertFalse($this->harness->users->findById(1)->isGdprConsent());
    }

    public function testGdprDeleteFailsWithWrongPasswordEvenWhenUserFound(): void
    {
        $user = $this->createUser('gina', 'gina@example.test', 'correct-password');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->gdprDelete(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprDeleteForm($this->harness->translator),
                    ['password' => 'wrong-password'],
                ),
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('Your personal information has been removed', $html);

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertFalse($reloaded->isGdprDeleted());
    }

    public function testGdprDeleteRejectsGuestIdentityEvenWhenItReportsAnExistingUserId(): void
    {
        $user = $this->createUser('gwen', 'gwen@example.test', 'correct-password');
        $userId = (int) $user->getId();
        // A guest should never be able to reach the deletion logic at all,
        // regardless of what getId() reports - the guard must check
        // instanceof GuestIdentityInterface directly rather than relying on
        // the id lookup incidentally failing.
        $this->harness->currentUser->overrideIdentity(new SettingsGuestWithIdIdentity((string) $userId));

        $response = $this->harness->settingsController->gdprDelete(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprDeleteForm($this->harness->translator),
                    ['password' => 'correct-password'],
                ),
            ),
        );

        $this->assertStringNotContainsString('Your personal information has been removed', $this->harness->responseBody($response));

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertFalse($reloaded->isGdprDeleted());
    }

    public function testGdprDeleteSuccessAnonymizesUserAndDispatchesEventsTwice(): void
    {
        $user = $this->createUser('holly', 'holly@example.test', 'correct-password');
        $originalAuthKey = $user->getAuthKey();
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->gdprDelete(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprDeleteForm($this->harness->translator),
                    ['password' => 'correct-password'],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Your personal information has been removed');

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('GDPR' . $userId, $reloaded->getUsername());
        $this->assertSame('GDPR' . $userId . '@example.com', $reloaded->getEmail());
        $this->assertTrue($reloaded->isGdprDeleted());
        $this->assertTrue($reloaded->isBlocked());
        $this->assertNotSame($originalAuthKey, $reloaded->getAuthKey());

        $gdprEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof GdprEvent,
        ));
        $this->assertCount(2, $gdprEvents);
    }

    public function testGdprDeleteUsesZeroWhenIdentityIdIsNull(): void
    {
        $passwordHash = (new PasswordHasher())->hash('ghost-password');
        $this->insertGhostUser(-1, 'ghost-negative', $passwordHash);
        $this->insertGhostUser(1, 'ghost-one', $passwordHash);
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->gdprDelete(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprDeleteForm($this->harness->translator),
                    ['password' => 'ghost-password'],
                ),
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('Your personal information has been removed', $html);

        $ghostNegative = $this->harness->users->findById(-1);
        $this->assertFalse($ghostNegative->isGdprDeleted());

        $ghostOne = $this->harness->users->findById(1);
        $this->assertFalse($ghostOne->isGdprDeleted());
    }

    public function testNetworksActionFiltersOutFalsyProviderNamesBeforeExcluding(): void
    {
        // Using a falsy provider identifier ("0") lets us distinguish the array_filter()
        // step: with it, "0" is dropped from the excluded-providers list, so the matching
        // auth client is NOT excluded and its connect button is rendered; without it,
        // "0" survives and the client is (wrongly) excluded.
        $user = $this->createUser('mona', 'mona@example.test');
        $this->createGhostSocialAccount((int) $user->getId(), '0');
        $this->harness->currentUser->overrideIdentity($user);

        $registry = new \YiiRocks\Voyti\AuthClient\AuthClientRegistry(
            new SettingsFakeAuthClient('0', 'Zero Provider'),
        );
        $controller = $this->buildSettingsControllerWithAuthClientRegistry($registry);

        $response = $controller->networks(
            $this->harness->request(Method::GET),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('Zero Provider', $html);
    }

    public function testNetworksActionRendersConnectedAccountsList(): void
    {
        $user = $this->createUser('ivy', 'ivy@example.test');
        $this->createGhostSocialAccount((int) $user->getId(), 'mastodon');
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->networks(
            $this->harness->request(Method::GET),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('mastodon', $html);
        $this->assertStringNotContainsString('No connected networks', $html);
    }

    public function testNetworksActionUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->createGhostSocialAccount(-1, 'ghost-provider-negative');
        $this->createGhostSocialAccount(1, 'ghost-provider-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->networks(
            $this->harness->request(Method::GET),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('No connected networks', $html);
        $this->assertStringNotContainsString('ghost-provider-negative', $html);
        $this->assertStringNotContainsString('ghost-provider-one', $html);
    }

    public function testUserProfileGetPrefillsFormFromExistingProfile(): void
    {
        $user = $this->createUser('jack', 'jack@example.test');
        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName('Real Name');
        $profile->setPublicEmail('public@profile.test');
        $profile->setGravatarEmail('gravatar@profile.test');
        $profile->setLocation('Some City');
        $profile->setWebsite('https://example.test');
        $profile->setTimezone('Europe/Amsterdam');
        $profile->setBio('Some bio text');
        $profile->save();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->userProfile(
            $this->harness->request(Method::GET),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('value="Real Name"', $html);
        $this->assertStringContainsString('value="public@profile.test"', $html);
        $this->assertStringContainsString('value="gravatar@profile.test"', $html);
        $this->assertStringContainsString('value="Some City"', $html);
        $this->assertStringContainsString('value="https://example.test"', $html);
        $this->assertStringContainsString('value="Europe/Amsterdam" selected', $html);
        $this->assertStringContainsString('>Some bio text<', $html);
    }

    public function testUserProfileGetUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->userProfile(
            $this->harness->request(Method::GET),
        );

        $this->assertResponseContains($response, 'User not found');
    }

    public function testUserProfilePostAssignsUserIdToNewlyCreatedProfile(): void
    {
        // Create two other users first so the target user's id is guaranteed to differ
        // from the auto-assigned SQLite rowid a freshly-inserted user_profile row would
        // get if setUserId() were never called. This lets us distinguish a profile saved
        // with the correct user_id from one saved with an unrelated auto-generated id.
        $this->createUser('other-one', 'other-one@example.test');
        $this->createUser('other-two', 'other-two@example.test');
        $user = $this->createUser('laura', 'laura@example.test');
        $userId = (int) $user->getId();
        $this->assertNotSame(1, $userId);
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->userProfile(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new UserProfileForm($this->harness->translator),
                    [
                        'name' => 'Laura Example',
                        'publicEmail' => '',
                        'gravatarEmail' => '',
                        'location' => '',
                        'website' => '',
                        'timezone' => '',
                        'bio' => '',
                    ],
                ),
            ),
        );

        $this->assertSame(302, $response->getStatusCode());

        $profile = $this->harness->userProfiles->findByUserId($userId);
        $this->assertInstanceOf(UserProfile::class, $profile);
        $this->assertSame($userId, $profile->getUserId());
        $this->assertSame('Laura Example', $profile->getName());

        $this->assertNull($this->harness->userProfiles->findByUserId(1));
    }

    public function testUserProfilePostPersistsAllProfileFields(): void
    {
        $user = $this->createUser('karen', 'karen@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->userProfile(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new UserProfileForm($this->harness->translator),
                    [
                        'name' => 'Karen Example',
                        'publicEmail' => 'karen.public@example.test',
                        'gravatarEmail' => 'karen.gravatar@example.test',
                        'location' => 'Wonderland',
                        'website' => 'https://karen.example.test',
                        'timezone' => 'Europe/Amsterdam',
                        'bio' => 'Hello there',
                    ],
                ),
            ),
        );

        $this->assertSame(302, $response->getStatusCode());

        $profile = $this->harness->userProfiles->findByUserId($userId);
        $this->assertInstanceOf(UserProfile::class, $profile);
        $this->assertSame('Karen Example', $profile->getName());
        $this->assertSame('karen.public@example.test', $profile->getPublicEmail());
        $this->assertSame('karen.gravatar@example.test', $profile->getGravatarEmail());
        $this->assertSame('Wonderland', $profile->getLocation());
        $this->assertSame('https://karen.example.test', $profile->getWebsite());
        $this->assertSame('Europe/Amsterdam', $profile->getTimezone());
        $this->assertSame('Hello there', $profile->getBio());
    }

    private function assertResponseContains(\Psr\Http\Message\ResponseInterface $response, string $expected): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($expected, $this->harness->responseBody($response));
    }

    private function buildSettingsControllerWithAuthClientRegistry(
        \YiiRocks\Voyti\AuthClient\AuthClientRegistry $registry,
    ): \YiiRocks\Voyti\Controller\SettingsController {
        return new \YiiRocks\Voyti\Controller\SettingsController(
            $this->harness->translator,
            $this->harness->webViewRenderer,
            $this->harness->users,
            $this->harness->userProfiles,
            $this->harness->socialAccounts,
            $this->harness->passwordHasher,
            new \Yiisoft\Validator\Validator(),
            $this->harness->eventDispatcher,
            $this->harness->url,
            $this->harness->moduleConfig,
            $registry,
            $this->harness->emailChangeStrategyFactory,
            $this->harness->qrCodeUriGeneratorService,
            $this->harness->emailChangeService,
            $this->harness->userTokens,
            $this->harness->hydrator,
            $this->harness->currentUser,
            new \Nyholm\Psr7\Factory\Psr17Factory(),
        );
    }

    /**
     * Builds a SettingsController wired against a real {@see \Yiisoft\Validator\Validator}
     * instead of the harness's always-valid fake, so invalid-submission behaviour can be
     * exercised. All other collaborators are reused from the shared harness.
     */
    private function buildSettingsControllerWithRealValidator(): \YiiRocks\Voyti\Controller\SettingsController
    {
        return new \YiiRocks\Voyti\Controller\SettingsController(
            $this->harness->translator,
            $this->harness->webViewRenderer,
            $this->harness->users,
            $this->harness->userProfiles,
            $this->harness->socialAccounts,
            $this->harness->passwordHasher,
            new \Yiisoft\Validator\Validator(),
            $this->harness->eventDispatcher,
            $this->harness->url,
            $this->harness->moduleConfig,
            new \YiiRocks\Voyti\AuthClient\AuthClientRegistry(),
            $this->harness->emailChangeStrategyFactory,
            $this->harness->qrCodeUriGeneratorService,
            $this->harness->emailChangeService,
            $this->harness->userTokens,
            $this->harness->hydrator,
            $this->harness->currentUser,
            new \Nyholm\Psr7\Factory\Psr17Factory(),
        );
    }

    private function createGhostSocialAccount(int $userId, string $provider): void
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId('client-' . $provider);
        $account->setUserId($userId);
        $account->setCreatedAt(time());
        $account->save();
    }

    private function createSchema(ConnectionInterface $db): void
    {
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(32) NOT NULL,
            auth_tf_enabled INTEGER NOT NULL DEFAULT 0,
            auth_tf_key VARCHAR(64),
            auth_tf_type VARCHAR(20),
            blocked_at INTEGER,
            confirmed_at INTEGER,
            created_at INTEGER NOT NULL,
            flags INTEGER NOT NULL DEFAULT 0,
            gdpr_consent INTEGER NOT NULL DEFAULT 0,
            gdpr_consent_date INTEGER,
            gdpr_deleted INTEGER NOT NULL DEFAULT 0,
            last_login_at INTEGER,
            last_login_ip VARCHAR(45),
            password_changed_at INTEGER,
            registration_ip VARCHAR(45),
            unconfirmed_email VARCHAR(255),
            updated_at INTEGER NOT NULL
        )')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_profile}} (
            user_id INTEGER NOT NULL PRIMARY KEY,
            bio TEXT,
            gravatar_email VARCHAR(255),
            location VARCHAR(255),
            name VARCHAR(255),
            public_email VARCHAR(255),
            timezone VARCHAR(40),
            website VARCHAR(255)
        )')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_social_account}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            provider VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            code VARCHAR(32),
            email VARCHAR(255),
            username VARCHAR(255),
            data TEXT,
            created_at INTEGER NOT NULL
        )')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_token}} (
            user_id INTEGER NOT NULL,
            code VARCHAR(32) NOT NULL,
            type INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, code, type)
        )')->execute();
    }

    private function createUser(string $username, string $email, string $password = 'secret123'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash((new PasswordHasher())->hash($password));
        $user->setAuthKey(bin2hex(random_bytes(16)));
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setConfirmedAt(time());
        $user->save();

        return $user;
    }

    private function dropSchema(ConnectionInterface $db): void
    {
        $db->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_social_account}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
    }

    private function insertGhostUser(int $id, string $username, ?string $passwordHash = null, bool $gdprConsent = false): void
    {
        $this->db->createCommand()->insert('{{%user}}', [
            'id' => $id,
            'username' => $username,
            'email' => $username . '@ghost.test',
            'password_hash' => $passwordHash ?? 'unused-hash',
            'auth_key' => 'ghost-auth-key-' . $id,
            'created_at' => time(),
            'updated_at' => time(),
            'gdpr_consent' => $gdprConsent ? 1 : 0,
        ])->execute();
    }
}

final class SettingsFakeAuthClient implements \YiiRocks\Voyti\AuthClient\AuthClientInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $title,
    ) {
    }

    public function fetchUserAttributes(string $code, string $redirectUri, \YiiRocks\Voyti\Http\ClientInterface $httpClient): array
    {
        return [];
    }

    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        return 'https://example.test/authorize';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isEnabled(): bool
    {
        return true;
    }
}

final class SettingsGuestWithIdIdentity implements GuestIdentityInterface
{
    public function __construct(private readonly string $id)
    {
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}

final class SettingsNullIdIdentity implements IdentityInterface
{
    public function getId(): ?string
    {
        return null;
    }
}
