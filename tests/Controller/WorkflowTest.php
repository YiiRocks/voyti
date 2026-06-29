<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Listener\SessionHistoryListener;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSessionHistory\UserSessionHistoryDecorator;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\FakeHttpClient;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;
use Yiisoft\Security\PasswordHasher;

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

    public function testAdminAssignmentsAndSessionHistoryAndDeleteFlow(): void
    {
        $user = $this->registerAndConfirmUser('helen', 'helen@example.test', 'secret123');
        $this->harness->seedRbacRole('manager');
        $this->harness->addSessionHistory($user, 'session-1', '198.51.100.10', 'Test Agent/1.0');

        $assignmentsResponse = $this->harness->adminController->assignments(
            $this->harness->request(Method::GET),
            (int) $user->getId(),
        );
        $this->assertResponseContains($assignmentsResponse, 'Assignments');
        $this->assertStringContainsString('manager', $this->harness->responseBody($assignmentsResponse));

        $assignmentsPostResponse = $this->harness->adminController->assignments(
            $this->harness->request(
                Method::POST,
                ['items' => ['manager']],
            ),
            (int) $user->getId(),
        );
        $this->assertResponseContains($assignmentsPostResponse, 'Assignments');
        $this->assertTrue($this->harness->authHelper->hasRole((int) $user->getId(), 'manager'));

        $historyResponse = $this->harness->adminController->userSessionHistory((int) $user->getId());
        $this->assertResponseContains($historyResponse, 'Session history');
        $historyHtml = $this->harness->responseBody($historyResponse);
        $this->assertStringContainsString('198.51.100.10', $historyHtml);
        $this->assertStringContainsString('Test Agent/1.0', $historyHtml);

        $terminateResponse = $this->harness->adminController->terminateSessions((int) $user->getId());
        $this->assertResponseContains($terminateResponse, 'Sessions have been terminated');
        $this->assertSame([], $this->harness->userSessionHistory->findByUserId((int) $user->getId()));

        $deleteResponse = $this->harness->adminController->delete(
            $this->harness->request(Method::POST),
            (int) $user->getId(),
        );
        $this->assertResponseContains($deleteResponse, 'User has been deleted');
        $this->assertNull($this->harness->users->findById((int) $user->getId()));
    }

    public function testAdminBlockConfirmPasswordResetAndPasswordChangeActions(): void
    {
        $response = $this->harness->adminController->create(
            $this->harness->request(
                Method::POST,
                [
                    'username' => 'ivan',
                    'email' => 'ivan@example.test',
                    'password' => 'secret123',
                ],
            ),
        );
        $this->assertResponseContains($response, 'User has been created');

        $user = $this->harness->users->findByEmail('ivan@example.test');
        $this->assertInstanceOf(User::class, $user);
        $userId = (int) $user->getId();

        $confirmResponse = $this->harness->adminController->confirm($userId);
        $this->assertResponseContains($confirmResponse, 'User has been confirmed');
        $confirmedUser = $this->harness->users->findById($userId);
        $this->assertTrue($confirmedUser->isConfirmed());

        $forcePasswordChangeResponse = $this->harness->adminController->forcePasswordChange($userId);
        $this->assertResponseContains($forcePasswordChangeResponse, 'User will be required to change password at next login');
        $expiredUser = $this->harness->users->findById($userId);
        $this->assertSame(0, $expiredUser->getPasswordChangedAt());

        $passwordResetResponse = $this->harness->adminController->passwordReset($userId);
        $this->assertResponseContains($passwordResetResponse, 'Recovery message sent');
        $recoveryTokens = $this->harness->userTokens->findByUserId($userId);
        $this->assertCount(1, $recoveryTokens);
        $recoveryToken = $recoveryTokens[array_key_first($recoveryTokens)];
        $this->assertInstanceOf(UserToken::class, $recoveryToken);
        $this->assertSame(UserToken::TYPE_RECOVERY, $recoveryToken->getType());

        $blockResponse = $this->harness->adminController->block($userId);
        $this->assertResponseContains($blockResponse, 'User block status has been updated');
        $blockedUser = $this->harness->users->findById($userId);
        $this->assertTrue($blockedUser->isBlocked());
    }

    public function testAdminCreateActionCreatesUserAndConfirmationToken(): void
    {
        $response = $this->harness->adminController->create(
            $this->harness->request(
                Method::POST,
                [
                    'username' => 'grace',
                    'email' => 'grace@example.test',
                    'password' => 'secret123',
                ],
            ),
        );

        $this->assertResponseContains($response, 'User has been created');

        $user = $this->harness->users->findByEmail('grace@example.test');
        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse($user->isConfirmed());
        $this->assertCount(1, $this->harness->userTokens->findByUserId((int) $user->getId()));
        $this->assertCount(1, $this->harness->mailer->messages());
    }

    public function testAdminCreateViewRendersWithoutMissingModel(): void
    {
        $response = $this->harness->adminController->create(
            $this->harness->request(Method::GET),
        );

        $html = $this->harness->responseBody($response);

        $this->assertStringContainsString('Create user', $html);
        $this->assertStringNotContainsString('Undefined variable', $html);
    }

    public function testAdminDeleteActionPreventsDeletingSelf(): void
    {
        $user = $this->registerAndConfirmUser('laura', 'laura@example.test', 'secret123');
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->adminController->delete(
            $this->harness->request(Method::POST),
            (int) $user->getId(),
        );

        $this->assertResponseContains($response, 'You cannot delete your own account');
        $this->assertNotNull($this->harness->users->findById((int) $user->getId()));
    }

    public function testAdminIndexRouteActionRendersWithoutMissingRouteNames(): void
    {
        $html = $this->harness->webViewRenderer
            ->withViewPath($this->harness->moduleConfig->viewPath)
            ->renderAsString('admin/index', [
            'users' => [],
            'filters' => ['username' => '', 'email' => '', 'status' => ''],
            'totalPages' => 1,
            'currentPage' => 1,
            'config' => $this->harness->moduleConfig,
            'translator' => $this->harness->translator,
            'url' => $this->harness->url,
        ]);

        $this->assertStringContainsString('voyti-admin-index', $html);
        $this->assertStringContainsString('voyti/admin', $html);
    }

    public function testAdminInfoViewRendersWithoutUndefinedUserProfile(): void
    {
        $user = $this->registerAndConfirmUser('eve', 'eve@example.test', 'secret123');

        $response = $this->harness->adminController->info((int) $user->getId());
        $html = $this->harness->responseBody($response);

        $this->assertStringContainsString($user->getUsername(), $html);
        $this->assertStringNotContainsString('Undefined variable', $html);
    }

    public function testAdminSwitchIdentityActionSwitchesCurrentUser(): void
    {
        $admin = $this->registerAndConfirmUser('jane', 'jane@example.test', 'secret123');
        $target = $this->registerAndConfirmUser('kate', 'kate@example.test', 'secret123');

        $this->harness->currentUser->login($admin);
        $response = $this->harness->adminController->switchIdentity((int) $target->getId());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame((string) $target->getId(), $this->harness->currentUser->getId());
        $this->assertSame((string) $admin->getId(), $this->harness->session->get($this->harness->moduleConfig->switchIdentitySessionKey));
    }

    public function testAdminUpdateViewRendersUsernameInTitle(): void
    {
        $user = $this->registerAndConfirmUser('frank', 'frank@example.test', 'secret123');

        $response = $this->harness->adminController->update(
            $this->harness->request(Method::GET),
            (int) $user->getId(),
        );
        $html = $this->harness->responseBody($response);

        $this->assertStringContainsString('Update user:', $html);
        $this->assertStringContainsString($user->getUsername(), $html);
    }

    public function testGdprConsentDeleteAndAccountDeletionFlow(): void
    {
        $user = $this->registerAndConfirmUser('carol', 'carol@example.test', 'secret123');
        $this->harness->currentUser->overrideIdentity($user);

        $consentPage = $this->harness->settingsController->gdprConsent(
            $this->harness->request(Method::GET),
        );
        $this->assertResponseContains($consentPage, 'GDPR Consent');

        $consentResponse = $this->harness->settingsController->gdprConsent(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new GdprConsentForm($this->harness->translator),
                    ['consent' => true],
                ),
            ),
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

    public function testLoginViewIncludesCsrfToken(): void
    {
        $response = $this->harness->securityController->login(
            $this->harness->request(Method::GET),
        );

        $html = $this->harness->responseBody($response);

        $this->assertMatchesRegularExpression('/name="_csrf" value="[^"]+"/', $html);
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

    public function testProfileUpdateAndEmailChangeFlow(): void
    {
        $user = $this->registerAndConfirmUser('bob', 'bob@example.test', 'secret123');
        $this->harness->currentUser->overrideIdentity($user);

        $accountViewResponse = $this->harness->settingsController->account(
            $this->harness->request(Method::GET),
        );
        $accountViewHtml = $this->harness->responseBody($accountViewResponse);
        $this->assertStringContainsString('Account settings', $accountViewHtml);
        $this->assertStringNotContainsString('currentPassword', $accountViewHtml);
        $this->assertStringNotContainsString('authTfEnabled', $accountViewHtml);
        $this->assertStringNotContainsString('Undefined variable', $accountViewHtml);

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
        $this->assertSame('', $response->getHeaderLine('Set-Cookie'));
    }

    public function testRememberMeSetsPersistentCookieAndLogoutExpiresIt(): void
    {
        $user = $this->registerAndConfirmUser('nina', 'nina@example.test', 'secret123');
        $originalAuthKey = $user->getAuthKey();

        $loginResponse = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'login' => 'nina@example.test',
                        'password' => 'secret123',
                        'rememberMe' => true,
                    ],
                ),
                serverParams: ['REMOTE_ADDR' => $this->remoteAddr],
            ),
        );

        $rememberCookie = $loginResponse->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin=', $rememberCookie);
        $this->assertStringContainsString('Max-Age=1209600', $rememberCookie);

        $logoutResponse = $this->harness->securityController->logout();
        $expiredCookie = $logoutResponse->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin=', $expiredCookie);
        $this->assertStringContainsString('Expires=', $expiredCookie);
        $this->assertStringContainsString('Max-Age=-', $expiredCookie);

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertNotSame($originalAuthKey, $reloaded->getAuthKey());
    }

    public function testSocialAuthenticationDispatchesLoginEventAndWritesSessionHistory(): void
    {
        $oauthHttpClient = new FakeHttpClient();
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                enableRegistration: true,
                enableEmailConfirmation: true,
                enableGdprCompliance: true,
                allowAccountDelete: true,
                allowPasswordRecovery: true,
                allowAdminPasswordRecovery: true,
                emailChangeStrategy: 1,
                enableSessionHistory: true,
                enableSocialNetworkRegistration: true,
                socialNetworkClients: [
                    'github' => [
                        'clientId' => 'github-client-id',
                        'clientSecret' => 'github-client-secret',
                    ],
                ],
            ),
            $oauthHttpClient,
        );

        $user = $this->registerAndConfirmUser('frank', 'frank@example.test', 'secret123');
        $socialAccount = new UserSocialAccount();
        $socialAccount->setProvider('github');
        $socialAccount->setClientId('gh-123');
        $socialAccount->setUserId((int) $user->getId());
        $socialAccount->setCreatedAt(time());
        $socialAccount->save();

        $redirectResponse = $this->harness->securityController->auth(
            $this->harness->request(Method::GET),
            'github',
        );
        $this->assertSame(302, $redirectResponse->getStatusCode());

        $location = $redirectResponse->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        $this->assertIsString($state);

        $oauthHttpClient->queue('POST', 'https://github.com/login/oauth/access_token', [
            'access_token' => 'oauth-token',
        ]);
        $oauthHttpClient->queue('GET', 'https://api.github.com/user', [
            'id' => 'gh-123',
            'login' => 'frank-gh',
            'name' => 'Frank Example',
            'email' => 'frank@example.test',
        ]);

        $response = $this->harness->securityController->auth(
            $this->harness->request(
                Method::GET,
                queryParams: [
                    'code' => 'oauth-code',
                    'state' => $state,
                ],
                serverParams: ['REMOTE_ADDR' => $this->remoteAddr],
            ),
            'github',
        );
        $this->assertResponseContains($response, 'Authenticated');

        $this->replayAfterLoginListeners();

        $events = array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof AfterLoginEvent,
        );
        $this->assertCount(1, $events);

        $history = $this->harness->userSessionHistory->findByUserId((int) $user->getId());
        $this->assertCount(1, $history);
        $this->assertStringStartsWith('test-session-', $history[0]->getSessionId());
        $this->assertSame($this->remoteAddr, $history[0]->getIp());
    }

    public function testLoginPageRendersConfiguredSocialProviders(): void
    {
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                socialNetworkClients: [
                    'github' => [
                        'clientId' => 'github-client-id',
                        'clientSecret' => 'github-client-secret',
                    ],
                ],
            ),
        );

        $response = $this->harness->securityController->login(
            $this->harness->request(Method::GET),
        );

        $this->assertResponseContains($response, 'GitHub');
        $this->assertStringContainsString('/voyti/auth/github', $this->harness->responseBody($response));
    }

    public function testSocialAuthenticationForNewUserRedirectsToConnectAndLinksOnRegistration(): void
    {
        $oauthHttpClient = new FakeHttpClient();
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                enableRegistration: true,
                enableEmailConfirmation: true,
                enableGdprCompliance: true,
                allowAccountDelete: true,
                allowPasswordRecovery: true,
                allowAdminPasswordRecovery: true,
                emailChangeStrategy: 1,
                enableSocialNetworkRegistration: true,
                socialNetworkClients: [
                    'github' => [
                        'clientId' => 'github-client-id',
                        'clientSecret' => 'github-client-secret',
                    ],
                ],
            ),
            $oauthHttpClient,
        );

        $redirectResponse = $this->harness->securityController->auth(
            $this->harness->request(Method::GET),
            'github',
        );
        $this->assertSame(302, $redirectResponse->getStatusCode());

        $location = $redirectResponse->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        $this->assertIsString($state);

        $oauthHttpClient->queue('POST', 'https://github.com/login/oauth/access_token', [
            'access_token' => 'oauth-token',
        ]);
        $oauthHttpClient->queue('GET', 'https://api.github.com/user', [
            'id' => 'gh-new',
            'login' => 'new-gh-user',
            'name' => 'New User',
            'email' => 'new-user@example.test',
        ]);

        $callbackResponse = $this->harness->securityController->auth(
            $this->harness->request(
                Method::GET,
                queryParams: [
                    'code' => 'oauth-code',
                    'state' => $state,
                ],
            ),
            'github',
        );
        $this->assertSame(302, $callbackResponse->getStatusCode());

        $connectLocation = $callbackResponse->getHeaderLine('Location');
        $connectCode = basename((string) parse_url($connectLocation, PHP_URL_PATH));
        $this->assertNotSame('', $connectCode);

        $connectPage = $this->harness->registrationController->connect(
            $this->harness->request(Method::GET),
            $connectCode,
        );
        $this->assertResponseContains($connectPage, 'Connect account');
        $this->assertResponseContains($connectPage, 'github');

        $registerResponse = $this->harness->registrationController->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new \YiiRocks\Voyti\Form\Auth\RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'username' => 'new-user',
                        'email' => 'new-user@example.test',
                        'password' => 'secret123',
                        'gdprConsent' => true,
                    ],
                ),
            ),
        );
        $this->assertResponseContains($registerResponse, 'Account created. Check your email for the confirmation link.');

        $registeredUser = $this->harness->users->findByEmail('new-user@example.test');
        $this->assertInstanceOf(User::class, $registeredUser);

        $account = $this->harness->socialAccounts->findByProviderAndClientId('github', 'gh-new');
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertSame((int) $registeredUser->getId(), $account->getUserId());
        $this->assertNull($account->getCode());
    }

    public function testNetworksPageUsesConnectViewAndExcludesConnectedProviders(): void
    {
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                socialNetworkClients: [
                    'github' => [
                        'clientId' => 'github-client-id',
                        'clientSecret' => 'github-client-secret',
                    ],
                    'google' => [
                        'clientId' => 'google-client-id',
                        'clientSecret' => 'google-client-secret',
                    ],
                ],
            ),
        );

        $user = $this->registerAndConfirmUser('mia', 'mia@example.test', 'secret123');
        $this->harness->currentUser->login($user);

        $socialAccount = new UserSocialAccount();
        $socialAccount->setProvider('github');
        $socialAccount->setClientId('gh-mia');
        $socialAccount->setUserId((int) $user->getId());
        $socialAccount->setCreatedAt(time());
        $socialAccount->save();

        $response = $this->harness->settingsController->networks(
            $this->harness->request(Method::GET),
        );

        $this->assertResponseContains($response, 'Networks');
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('Google', $html);
        $this->assertStringContainsString('/voyti/connect/google', $html);
        $this->assertStringNotContainsString('/voyti/connect/github', $html);
    }

    public function testAuthenticatedUserCanConnectSocialAccount(): void
    {
        $oauthHttpClient = new FakeHttpClient();
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                socialNetworkClients: [
                    'github' => [
                        'clientId' => 'github-client-id',
                        'clientSecret' => 'github-client-secret',
                    ],
                ],
            ),
            $oauthHttpClient,
        );

        $user = $this->registerAndConfirmUser('zoe', 'zoe@example.test', 'secret123');
        $this->harness->currentUser->login($user);

        $redirectResponse = $this->harness->securityController->connect(
            $this->harness->request(Method::GET),
            'github',
        );
        $this->assertSame(302, $redirectResponse->getStatusCode());

        $location = $redirectResponse->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        $this->assertIsString($state);

        $oauthHttpClient->queue('POST', 'https://github.com/login/oauth/access_token', [
            'access_token' => 'oauth-token',
        ]);
        $oauthHttpClient->queue('GET', 'https://api.github.com/user', [
            'id' => 'gh-zoe',
            'login' => 'zoe-gh',
            'name' => 'Zoe Example',
            'email' => 'zoe@example.test',
        ]);

        $response = $this->harness->securityController->connect(
            $this->harness->request(
                Method::GET,
                queryParams: [
                    'code' => 'oauth-code',
                    'state' => $state,
                ],
            ),
            'github',
        );
        $this->assertResponseContains($response, 'Authenticated');

        $account = $this->harness->socialAccounts->findByProviderAndClientId('github', 'gh-zoe');
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertSame((int) $user->getId(), $account->getUserId());
    }

    public function testTwoFactorConfirmationDispatchesLoginEventAndWritesSessionHistory(): void
    {
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                enableRegistration: true,
                enableEmailConfirmation: true,
                enableGdprCompliance: true,
                allowAccountDelete: true,
                allowPasswordRecovery: true,
                allowAdminPasswordRecovery: true,
                emailChangeStrategy: 1,
                enableSessionHistory: true,
                enableTwoFactorAuthentication: true,
            ),
        );

        $user = $this->registerAndConfirmUser('eve', 'eve@example.test', 'secret123');
        $user->setAuthTfEnabled(true);
        $user->save();

        $loginResponse = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'login' => 'eve@example.test',
                        'password' => 'secret123',
                        'rememberMe' => true,
                    ],
                ),
                serverParams: ['REMOTE_ADDR' => $this->remoteAddr],
            ),
        );
        $this->assertStringContainsString('confirm', strtolower($this->harness->responseBody($loginResponse)));

        $confirmResponse = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'login' => 'eve@example.test',
                        'password' => 'secret123',
                    ],
                ),
                serverParams: ['REMOTE_ADDR' => $this->remoteAddr],
            ),
        );
        $this->assertResponseContains($confirmResponse, 'Authenticated');

        $this->replayAfterLoginListeners();

        $events = array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof AfterLoginEvent,
        );
        $this->assertCount(1, $events);

        $history = $this->harness->userSessionHistory->findByUserId((int) $user->getId());
        $this->assertCount(1, $history);
        $this->assertStringStartsWith('test-session-', $history[0]->getSessionId());
        $this->assertSame($this->remoteAddr, $history[0]->getIp());
    }

    private function assertResponseContains(\Psr\Http\Message\ResponseInterface $response, string $expected): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($expected, $this->harness->responseBody($response));
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

    private function replayAfterLoginListeners(): void
    {
        $listener = new SessionHistoryListener(
            new UserSessionHistoryDecorator(
                $this->harness->eventDispatcher,
                $this->harness->moduleConfig,
                $this->harness->session,
            ),
            $this->harness->moduleConfig,
        );

        foreach ($this->harness->eventDispatcher->events() as $event) {
            if ($event instanceof AfterLoginEvent) {
                $listener->onAfterLogin($event);
            }
        }
    }
}
