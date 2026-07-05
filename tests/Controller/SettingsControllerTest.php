<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use chillerlan\Authenticator\Authenticator;
use chillerlan\Authenticator\AuthenticatorOptions;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Event\User\UserProfileEvent;
use YiiRocks\Voyti\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\ModuleConfig;
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

        $this->assertRedirectWithFlash(
            $response,
            '/voyti/settings-account',
            'Your account details have been updated',
        );

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
        $this->insertSession($userId, 'dana-sess');
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->delete(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'Your account has been deleted');
        $this->assertNull($this->harness->users->findById($userId));
        $this->assertCount(0, UserSessionHistory::query()->where(['user_id' => $userId])->all());

        $userEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof UserEvent,
        ));
        $this->assertCount(2, $userEvents);

        $sessionEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof SessionEvent,
        ));
        $this->assertCount(1, $sessionEvents);
        $this->assertSame($userId, $sessionEvents[0]->getUserId());
        $this->assertSame(['type' => SessionEvent::SESSION_TERMINATED], $sessionEvents[0]->getData());
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

    public function testDisconnectRemovesAccountAndRedirectsWithFlash(): void
    {
        $user = $this->createUser('dana-network', 'dana-network@example.test');
        $this->createGhostSocialAccount((int) $user->getId(), 'mastodon');
        $this->harness->currentUser->overrideIdentity($user);

        $account = $this->harness->socialAccounts->findByProviderAndClientId('mastodon', 'client-mastodon');
        $this->assertInstanceOf(UserSocialAccount::class, $account);

        $response = $this->harness->settingsController->disconnect(
            $this->harness->request(Method::POST),
            (int) $account->getId(),
        );

        $this->assertRedirectWithFlash($response, '/voyti/settings-networks', 'Network has been disconnected');
        $this->assertNull($this->harness->socialAccounts->findByProviderAndClientId('mastodon', 'client-mastodon'));
    }

    public function testDisconnectReturnsNetworkNotFoundForUnknownAccountId(): void
    {
        $user = $this->createUser('elle-network', 'elle-network@example.test');
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->disconnect(
            $this->harness->request(Method::POST),
            999999,
        );

        $this->assertResponseContains($response, 'Network not found');
    }

    public function testDisconnectUsesZeroWhenIdentityIdIsNull(): void
    {
        $this->createGhostSocialAccount(-1, 'ghost-provider-negative');
        $this->createGhostSocialAccount(0, 'ghost-provider-zero');
        $this->createGhostSocialAccount(1, 'ghost-provider-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $zeroAccount = $this->harness->socialAccounts->findByProviderAndClientId('ghost-provider-zero', 'client-ghost-provider-zero');
        $this->assertInstanceOf(UserSocialAccount::class, $zeroAccount);

        $response = $this->harness->settingsController->disconnect(
            $this->harness->request(Method::POST),
            (int) $zeroAccount->getId(),
        );

        $this->assertRedirectWithFlash($response, '/voyti/settings-networks', 'Network has been disconnected');
        $this->assertNull($this->harness->socialAccounts->findByProviderAndClientId('ghost-provider-zero', 'client-ghost-provider-zero'));
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

        $this->assertRedirectWithFlash($response, '/voyti/gdpr-consent', 'GDPR consent has been saved');

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
        $this->insertSession($userId, 'holly-sess');
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
        $this->assertCount(0, UserSessionHistory::query()->where(['user_id' => $userId])->all());

        $gdprEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof GdprEvent,
        ));
        $this->assertCount(2, $gdprEvents);

        $sessionEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof SessionEvent,
        ));
        $this->assertCount(1, $sessionEvents);
        $this->assertSame($userId, $sessionEvents[0]->getUserId());
        $this->assertSame(['type' => SessionEvent::SESSION_TERMINATED], $sessionEvents[0]->getData());
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

    public function testGetUserIdUsesZeroFallbackWhenUserHasNoId(): void
    {
        // An unsaved user has a null id; the private getUserId() fallback must be
        // exactly 0 (not -1 or 1). save() always populates the primary key on
        // insert, so the null branch cannot be observed by going through the
        // delete()/gdprDelete() actions; we invoke the private method directly.
        $user = new User();
        $user->setUsername('unsaved');
        $user->setEmail('unsaved@example.test');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');

        $this->assertNull($user->getId());

        $method = new \ReflectionMethod(\YiiRocks\Voyti\Controller\SettingsController::class, 'getUserId');
        $result = $method->invoke($this->harness->settingsController, $user);

        $this->assertSame(0, $result);
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

    public function testTwoFactorDisableClearsAllTwoFactorFields(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('finn', 'finn@example.test');
        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('google');
        $user->setAuthTfKey('some-secret');
        $user->save();
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->twoFactorDisable(
            $this->harness->request(Method::POST),
        );

        $this->assertRedirectWithFlash(
            $response,
            '/voyti/settings-two-factor',
            'Two-factor authentication has been disabled',
        );

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertFalse($reloaded->isAuthTfEnabled());
        $this->assertNull($reloaded->getAuthTfKey());
        $this->assertNull($reloaded->getAuthTfType());
    }

    public function testTwoFactorDisableReturnsNotAuthenticatedForGuest(): void
    {
        $this->harness = $this->twoFactorHarness();

        $response = $this->harness->settingsController->twoFactorDisable(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'Not authenticated');
    }

    public function testTwoFactorDisableReturnsNotAvailableWhenFeatureDisabled(): void
    {
        $response = $this->harness->settingsController->twoFactorDisable(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'Not available');
    }

    public function testTwoFactorDisableReturnsUserNotFoundWhenIdentityIdIsNull(): void
    {
        $this->harness = $this->twoFactorHarness();
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->twoFactorDisable(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'User not found');
    }

    public function testTwoFactorEnableRetryWithGoogleMethodDiscardsLeftoverEmailCode(): void
    {
        // Reproduces the reported bug scenario directly: the user switched to the email
        // tab (leaving a 6-digit numeric code in the shared auth_tf_key column) and then
        // retries with the wrong TOTP code on the google tab. The retry path must discard
        // that leftover value and generate a fresh TOTP secret, not reuse the numeric code.
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('opal2', 'opal2@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $this->harness->settingsController->twoFactor(
            $this->harness->request(Method::GET, queryParams: ['method' => 'email']),
        );
        $leftoverEmailCode = $this->harness->users->findById($userId)?->getAuthTfKey();
        $this->assertNotNull($leftoverEmailCode);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $leftoverEmailCode);

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'google', 'code' => '000000']),
        );

        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString($leftoverEmailCode, $html);

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('google', $reloaded->getAuthTfType());
        $this->assertNotSame($leftoverEmailCode, $reloaded->getAuthTfKey());
        $this->assertNotNull($reloaded->getAuthTfKey());
    }

    public function testTwoFactorEnableReturnsNotAuthenticatedForGuest(): void
    {
        $this->harness = $this->twoFactorHarness();

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'Not authenticated');
    }

    public function testTwoFactorEnableReturnsNotAvailableWhenFeatureDisabled(): void
    {
        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'Not available');
    }

    public function testTwoFactorEnableReturnsUserNotFoundWhenIdentityIdIsNull(): void
    {
        $this->harness = $this->twoFactorHarness();
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST),
        );

        $this->assertResponseContains($response, 'User not found');
    }

    public function testTwoFactorEnableWithGoogleMethodTranslatesUnconfiguredValidatorError(): void
    {
        // No prior GET step: the user has no authTfKey at all, so CodeValidator hits its
        // "not configured" branch, which only ever produces translated text when
        // setTranslator() was actually called on it.
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('nash', 'nash@example.test');
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'google', 'code' => '000000']),
        );

        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('Two factor authentication is not configured.', $html);
        $this->assertStringNotContainsString('voyti.validator.two_factor_not_configured', $html);
    }

    public function testTwoFactorEnableWithInvalidEmailCodeShowsErrorAndDoesNotEnable(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('hank', 'hank@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $this->harness->settingsController->twoFactor(
            $this->harness->request(Method::GET, queryParams: ['method' => 'email']),
        );

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'email', 'code' => 'wrong-code']),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('Invalid verification code.', $html);

        // authTfType is already 'email' at this point: twoFactor(method=email) marks it as
        // the pending method as soon as the code is sent, before the user ever confirms it.
        // isAuthTfEnabled() is the only reliable "not enabled yet" signal.
        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertFalse($reloaded->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithInvalidGoogleCodeShowsErrorAndDoesNotEnable(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('jill', 'jill@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));
        $originalSecret = $this->harness->users->findById($userId)?->getAuthTfKey();
        $this->assertNotNull($originalSecret);

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'google', 'code' => '000000']),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString($originalSecret, $html);
        $this->assertStringContainsString('Invalid verification code.', $html);

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertFalse($reloaded->isAuthTfEnabled());
        $this->assertSame($originalSecret, $reloaded->getAuthTfKey());
    }

    public function testTwoFactorEnableWithValidEmailCodeEnablesEmailTwoFactor(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('gabi', 'gabi@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $this->harness->settingsController->twoFactor(
            $this->harness->request(Method::GET, queryParams: ['method' => 'email']),
        );

        $this->assertCount(1, $this->harness->mailer->messages());

        $sentUser = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $sentUser);
        $sentCode = $sentUser->getAuthTfKey();
        $this->assertNotNull($sentCode);

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'email', 'code' => $sentCode]),
        );

        $this->assertRedirectWithFlash(
            $response,
            '/voyti/settings-two-factor',
            'Two-factor authentication has been enabled',
        );

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue($reloaded->isAuthTfEnabled());
        $this->assertSame('email', $reloaded->getAuthTfType());
    }

    public function testTwoFactorEnableWithValidEmailCodeSetsTypeWhenNotAlreadyEmail(): void
    {
        // Bypasses the GET step (which would already mark authTfType as 'email') so the
        // setAuthTfType('email') call inside the success branch has an observable effect.
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('koa', 'koa@example.test');
        $userId = (int) $user->getId();
        $user->setAuthTfKey('654321');
        $user->save();
        $this->assertNull($user->getAuthTfType());
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'email', 'code' => '654321']),
        );

        $this->assertRedirectWithFlash(
            $response,
            '/voyti/settings-two-factor',
            'Two-factor authentication has been enabled',
        );

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('email', $reloaded->getAuthTfType());
    }

    public function testTwoFactorEnableWithValidGoogleCodeEnablesTotp(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('ivan', 'ivan@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));

        $secretUser = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $secretUser);
        $secret = $secretUser->getAuthTfKey();
        $this->assertNotNull($secret);

        $authenticator = new Authenticator(new AuthenticatorOptions());
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'google', 'code' => $code]),
        );

        $this->assertRedirectWithFlash(
            $response,
            '/voyti/settings-two-factor',
            'Two-factor authentication has been enabled',
        );

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue($reloaded->isAuthTfEnabled());
        $this->assertSame('google', $reloaded->getAuthTfType());
    }

    public function testTwoFactorEnableWithValidGoogleCodeSetsTypeWhenNotAlreadyGoogle(): void
    {
        // Bypasses the GET step (which would already mark authTfType as 'google') so the
        // setAuthTfType('google') call inside the success branch has an observable effect.
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('mabel', 'mabel@example.test');
        $userId = (int) $user->getId();

        $authenticator = new Authenticator(new AuthenticatorOptions());
        $secret = $authenticator->createSecret();
        $user->setAuthTfKey($secret);
        $user->setAuthTfType('email');
        $user->save();
        $this->harness->currentUser->overrideIdentity($user);

        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $response = $this->harness->settingsController->twoFactorEnable(
            $this->harness->request(Method::POST, ['method' => 'google', 'code' => $code]),
        );

        $this->assertRedirectWithFlash(
            $response,
            '/voyti/settings-two-factor',
            'Two-factor authentication has been enabled',
        );

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('google', $reloaded->getAuthTfType());
    }

    public function testTwoFactorGetDefaultMethodGeneratesQrCode(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('lena', 'lena@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $this->harness->mailer->messages());

        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertNotNull($reloaded->getAuthTfKey());

        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString((string) $reloaded->getAuthTfKey(), $html);
    }

    public function testTwoFactorGetEmailMethodSendsCodeAndShowsInstructions(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('mira', 'mira@example.test');
        $userId = (int) $user->getId();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->twoFactor(
            $this->harness->request(Method::GET, queryParams: ['method' => 'email']),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $this->harness->mailer->messages());
        $this->assertResponseContains($response, 'Enter the verification code sent to your email');

        // Marks 'email' as the pending method as soon as the code is sent, so the
        // TOTP QR page (default method) knows to regenerate a fresh secret instead
        // of reusing the leftover numeric email code.
        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('email', $reloaded->getAuthTfType());
    }

    public function testTwoFactorGetWhenAlreadyEnabledShowsCurrentMethod(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('noor', 'noor@example.test');
        $userId = (int) $user->getId();
        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('email');
        $user->setAuthTfKey('123456');
        $user->save();
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));

        $html = $this->harness->responseBody($response);
        $this->assertResponseContains($response, 'Two-factor authentication is enabled');
        $this->assertStringContainsString('Two-factor authentication via email', $html);
        $this->assertCount(0, $this->harness->mailer->messages());

        // The already-enabled branch must return immediately without touching the
        // stored key: falling through would treat the email code as a foreign TOTP
        // secret (or regenerate one), corrupting the value the user already has.
        $reloaded = $this->harness->users->findById($userId);
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('123456', $reloaded->getAuthTfKey());
        $this->assertSame('email', $reloaded->getAuthTfType());
    }

    public function testTwoFactorGetWhenAlreadyEnabledWithNullTypeDefaultsToGoogleMethod(): void
    {
        $this->harness = $this->twoFactorHarness();
        $user = $this->createUser('opal', 'opal@example.test');
        $user->setAuthTfEnabled(true);
        $user->setAuthTfKey('some-legacy-secret');
        $user->save();
        $this->assertNull($user->getAuthTfType());
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));

        $html = $this->harness->responseBody($response);
        $this->assertResponseContains($response, 'Two-factor authentication is enabled');
        $this->assertStringContainsString('Two-Factor Authentication)', $html);
        $this->assertStringNotContainsString('Two-factor authentication via email', $html);
    }

    public function testTwoFactorReturnsNotAuthenticatedForGuest(): void
    {
        $this->harness = $this->twoFactorHarness();

        $response = $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));

        $this->assertResponseContains($response, 'Not authenticated');
    }

    public function testTwoFactorReturnsNotAvailableWhenFeatureDisabled(): void
    {
        $response = $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));

        $this->assertResponseContains($response, 'Not available');
    }

    public function testTwoFactorReturnsUserNotFoundWhenIdentityIdIsNull(): void
    {
        $this->harness = $this->twoFactorHarness();
        $this->insertGhostUser(-1, 'ghost-negative');
        $this->insertGhostUser(1, 'ghost-one');
        $this->harness->currentUser->overrideIdentity(new SettingsNullIdIdentity());

        $response = $this->harness->settingsController->twoFactor($this->harness->request(Method::GET));

        $this->assertResponseContains($response, 'User not found');
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

        $this->assertRedirectWithFlash($response, '/voyti/settings', 'Your profile has been updated');

        $profile = $this->harness->userProfiles->findByUserId($userId);
        $this->assertInstanceOf(UserProfile::class, $profile);
        $this->assertSame($userId, $profile->getUserId());
        $this->assertSame('Laura Example', $profile->getName());

        $this->assertNull($this->harness->userProfiles->findByUserId(1));

        $profileEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof UserProfileEvent,
        ));
        $this->assertCount(1, $profileEvents);
        $this->assertSame($userId, $profileEvents[0]->getProfile()->getUserId());
        $this->assertSame('Laura Example', $profileEvents[0]->getProfile()->getName());
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

    private function assertRedirectWithFlash(\Psr\Http\Message\ResponseInterface $response, string $expectedLocation, string $expectedMessage): void
    {
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($expectedLocation, $response->getHeaderLine('Location'));
        $this->assertSame($expectedMessage, $this->harness->flash->get('success'));
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
            $this->harness->twoFactorEmailCodeService,
            $this->harness->emailChangeService,
            $this->harness->userTokens,
            $this->harness->hydrator,
            $this->harness->currentUser,
            new \Nyholm\Psr7\Factory\Psr17Factory(),
            new \YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService($this->harness->eventDispatcher),
            $this->harness->flash,
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
            $this->harness->twoFactorEmailCodeService,
            $this->harness->emailChangeService,
            $this->harness->userTokens,
            $this->harness->hydrator,
            $this->harness->currentUser,
            new \Nyholm\Psr7\Factory\Psr17Factory(),
            new \YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService($this->harness->eventDispatcher),
            $this->harness->flash,
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
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_session_history}} (
            user_id INTEGER NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            user_agent TEXT,
            ip VARCHAR(45),
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, session_id)
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
        $db->createCommand('DROP TABLE IF EXISTS {{%user_session_history}}')->execute();
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

    private function insertSession(int $userId, string $sessionId): void
    {
        $session = new UserSessionHistory();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();
    }

    private function twoFactorHarness(): ControllerHarness
    {
        return new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(enableTwoFactorAuthentication: true),
        );
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
