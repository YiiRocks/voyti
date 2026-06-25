<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;
use Yiisoft\Security\PasswordHasher;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;

final class WorkflowTest extends TestCase
{
    private ?ConnectionInterface $db = null;
    private ControllerHarness $harness;
    private string $remoteAddr = '198.51.100.42';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getDb();
        ConnectionProvider::set($this->db);
        $this->createSchema($this->db);

        $_SERVER['REMOTE_ADDR'] = $this->remoteAddr;
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

    public function testRegistrationConfirmationAndLoginFlow(): void
    {
        $user = $this->registerAndConfirmUser('alice', 'alice@example.test', 'secret123');

        $loginRequest = $this->harness->request(
            Method::POST,
            $this->harness->formPayload(
                new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                [
                    'login' => 'alice@example.test',
                    'password' => 'secret123',
                    'rememberMe' => false,
                ],
            ),
            serverParams: ['REMOTE_ADDR' => $this->remoteAddr],
        );

        $response = $this->harness->securityController->login($loginRequest);
        $this->assertResponseContains($response, 'Logged in');

        $this->assertFalse($this->harness->currentUser->isGuest());
        $this->assertSame((string) $user->getId(), $this->harness->currentUser->getId());

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertNotNull($reloaded->getLastLoginAt());
        $this->assertSame($this->remoteAddr, $reloaded->getLastLoginIp());

        $events = $this->harness->eventDispatcher->events();
        $this->assertNotEmpty(array_filter($events, static fn (object $event): bool => $event instanceof AfterRegisterEvent));
        $this->assertNotEmpty(array_filter($events, static fn (object $event): bool => $event instanceof AfterLoginEvent));
        $this->assertCount(1, $this->harness->mailer->messages());
    }

    public function testLoginViewIncludesCsrfToken(): void
    {
        $response = $this->harness->securityController->login(
            $this->harness->request(Method::GET),
        );

        $html = $this->harness->responseBody($response);

        $this->assertMatchesRegularExpression('/name="_csrf" value="[^"]+"/', $html);
    }

    public function testProfileUpdateAndEmailChangeFlow(): void
    {
        $user = $this->registerAndConfirmUser('bob', 'bob@example.test', 'secret123');
        $this->harness->currentUser->overrideIdentity($user);

        $profileResponse = $this->harness->settingsController->userProfile(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new UserProfileForm($this->harness->translator),
                    [
                        'name' => 'Bob Example',
                        'publicEmail' => 'public@example.test',
                        'bio' => 'Updated bio',
                    ],
                ),
            ),
        );
        $this->assertResponseContains($profileResponse, 'Your profile has been updated');

        $profile = $this->harness->userProfiles->findByUserId((int) $user->getId());
        $this->assertNotNull($profile);
        $this->assertSame('Bob Example', $profile->getName());
        $this->assertSame('public@example.test', $profile->getPublicEmail());
        $this->assertSame('Updated bio', $profile->getBio());

        $accountResponse = $this->harness->settingsController->account(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new SettingsForm($this->harness->translator),
                    [
                        'username' => 'bob-updated',
                        'email' => 'bob.new@example.test',
                        'password' => '',
                    ],
                ),
            ),
        );
        $this->assertResponseContains($accountResponse, 'Your account details have been updated');

        $updatedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertSame('bob-updated', $updatedUser->getUsername());
        $this->assertSame('bob@example.test', $updatedUser->getEmail());
        $this->assertSame('bob.new@example.test', $updatedUser->getUnconfirmedEmail());

        $tokens = $this->harness->userTokens->findByUserId((int) $user->getId());
        $confirmNewEmailToken = null;
        foreach ($tokens as $token) {
            if ($token->getType() === UserToken::TYPE_CONFIRM_NEW_EMAIL) {
                $confirmNewEmailToken = $token;
                break;
            }
        }
        $this->assertInstanceOf(UserToken::class, $confirmNewEmailToken);

        $confirmResponse = $this->harness->settingsController->confirm(
            $this->harness->request(Method::GET),
            $confirmNewEmailToken->getCode(),
        );
        $this->assertResponseContains($confirmResponse, 'Your email has been changed');

        $changedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $changedUser);
        $this->assertSame('bob.new@example.test', $changedUser->getEmail());
        $this->assertNull($changedUser->getUnconfirmedEmail());
        $this->assertCount(2, $this->harness->mailer->messages());
    }

    public function testGdprConsentDeleteAndAccountDeletionFlow(): void
    {
        $user = $this->registerAndConfirmUser('carol', 'carol@example.test', 'secret123');
        $this->harness->currentUser->overrideIdentity($user);

        $consentResponse = $this->harness->settingsController->gdprConsent(
            $this->harness->request(Method::POST),
        );
        $this->assertResponseContains($consentResponse, 'GDPR consent has been saved');

        $consentedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertTrue($consentedUser->isGdprConsent());
        $this->assertNotNull($consentedUser->getGdprConsentDate());

        $gdprDeleteResponse = $this->harness->settingsController->gdprDelete(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprDeleteForm($this->harness->translator),
                    ['password' => 'secret123'],
                ),
            ),
        );
        $this->assertResponseContains($gdprDeleteResponse, 'Your personal information has been removed');

        $gdprUser = $this->harness->users->findById((int) $user->getId());
        $this->assertTrue($gdprUser->isGdprDeleted());
        $this->assertTrue($gdprUser->isBlocked());
        $this->assertStringStartsWith('GDPR', $gdprUser->getUsername());
        $this->assertStringStartsWith('GDPR', $gdprUser->getEmail());

        $deleteResponse = $this->harness->settingsController->delete(
            $this->harness->request(Method::POST),
        );
        $this->assertResponseContains($deleteResponse, 'Your account has been deleted');

        $this->assertNull($this->harness->users->findById((int) $user->getId()));
        $this->assertNull($this->harness->userProfiles->findByUserId((int) $user->getId()));

        $events = $this->harness->eventDispatcher->events();
        $this->assertNotEmpty(array_filter($events, static fn (object $event): bool => $event instanceof GdprEvent));
    }

    public function testPasswordRecoveryRequestAndResetFlow(): void
    {
        $user = $this->registerAndConfirmUser('dave', 'dave@example.test', 'secret123');

        $requestResponse = $this->harness->recoveryController->request(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RecoveryForm($this->harness->moduleConfig, $this->harness->translator, RecoveryForm::SCENARIO_REQUEST),
                    ['email' => 'dave@example.test'],
                ),
            ),
        );
        $this->assertResponseContains($requestResponse, 'Recovery message sent');

        $tokens = $this->harness->userTokens->findByUserId((int) $user->getId());
        $recoveryToken = null;
        foreach ($tokens as $token) {
            if ($token->getType() === UserToken::TYPE_RECOVERY) {
                $recoveryToken = $token;
                break;
            }
        }
        $this->assertInstanceOf(UserToken::class, $recoveryToken);

        $resetResponse = $this->harness->recoveryController->reset(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RecoveryForm($this->harness->moduleConfig, $this->harness->translator, RecoveryForm::SCENARIO_RESET),
                    ['password' => 'new-secret123'],
                ),
            ),
            (int) $user->getId(),
            $recoveryToken->getCode(),
        );
        $this->assertResponseContains($resetResponse, 'Password has been changed');

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue((new PasswordHasher())->validate('new-secret123', $reloaded->getPasswordHash()));
        $this->assertNull($this->harness->userTokens->findByUserIdTypeAndCode((int) $user->getId(), $recoveryToken->getType(), $recoveryToken->getCode()));
    }

    private function registerAndConfirmUser(string $username, string $email, string $password): User
    {
        $registerResponse = $this->harness->registrationController->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new \YiiRocks\Voyti\Form\Auth\RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'gdprConsent' => true,
                    ],
                ),
            ),
        );
        $this->assertResponseContains($registerResponse, 'Account created. Check your email for the confirmation link.');

        $user = $this->harness->users->findByEmail($email);
        $this->assertInstanceOf(User::class, $user);

        $token = $this->harness->userTokens->findByUserId((int) $user->getId())[0] ?? null;
        $this->assertInstanceOf(UserToken::class, $token);

        $confirmResponse = $this->harness->registrationController->confirm(
            $this->harness->request(Method::GET),
            (int) $user->getId(),
            $token->getCode(),
        );
        $this->assertResponseContains($confirmResponse, 'Thank you, registration is now complete.');

        $confirmedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $confirmedUser);
        $this->assertTrue($confirmedUser->isConfirmed());

        return $confirmedUser;
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
            auth_tf_key VARCHAR(16),
            auth_tf_mobile_phone VARCHAR(32),
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
            gravatar_id VARCHAR(32),
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
            ip VARCHAR(45) NOT NULL,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, session_id)
        )')->execute();
    }

    private function dropSchema(ConnectionInterface $db): void
    {
        $db->createCommand('DROP TABLE IF EXISTS {{%user_session_history}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_social_account}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
    }

    private function assertResponseContains(\Psr\Http\Message\ResponseInterface $response, string $expected): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($expected, $this->harness->responseBody($response));
    }
}
