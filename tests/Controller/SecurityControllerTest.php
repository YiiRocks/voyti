<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use chillerlan\Authenticator\Authenticator;
use chillerlan\Authenticator\AuthenticatorOptions;
use chillerlan\Authenticator\Common\Base32;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\User\FormEvent;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\FakeHttpClient;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;

final class SecurityControllerTest extends TestCase
{
    private ?ConnectionInterface $db = null;
    private ControllerHarness $harness;
    private string $remoteAddr = '203.0.113.77';

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

    public function testConfirmComputesGoogleMethodWithoutCrashingWhenSessionUserNoLongerExists(): void
    {
        // findByUsernameOrEmail() legitimately returns null here (the login in the stale
        // session credentials was never registered), so the nullsafe operator on the
        // method-lookup line must short-circuit rather than call getAuthTfType() on null.
        $this->harness->session->set('credentials', [
            'login' => 'ghost-never-registered@example.test',
            'pwd' => 'whatever',
            'rememberMe' => false,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(Method::GET),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Authenticated', $this->harness->responseBody($response));
        $this->assertTrue($this->harness->currentUser->isGuest());
    }

    public function testConfirmDoesNotRememberWhenRememberMeIsOffString(): void
    {
        $user = $this->registerAndConfirmUser('petr', 'petr@example.test', 'secret123');
        $code = $this->enableTwoFactorAuth($user);

        $this->harness->session->set('credentials', [
            'login' => 'petr@example.test',
            'pwd' => 'secret123',
            'rememberMe' => 'off',
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => $code],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Authenticated');
        $this->assertSame('', $response->getHeaderLine('Set-Cookie'));
        $this->assertNull($this->harness->session->get('__auth_expire'));
    }

    public function testConfirmDoesNotRememberWhenRememberMeKeyIsMissing(): void
    {
        $user = $this->registerAndConfirmUser('ola', 'ola@example.test', 'secret123');
        $code = $this->enableTwoFactorAuth($user);

        $this->harness->session->set('credentials', [
            'login' => 'ola@example.test',
            'pwd' => 'secret123',
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => $code],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Authenticated');
        $this->assertSame('', $response->getHeaderLine('Set-Cookie'));
        $this->assertNull($this->harness->session->get('__auth_expire'));
    }

    public function testConfirmFailsAndKeepsCredentialsWhenTwoFactorCodeIsWrong(): void
    {
        $user = $this->registerAndConfirmUser('ivo', 'ivo@example.test', 'secret123');
        $this->enableTwoFactorAuth($user);

        $this->harness->session->set('credentials', [
            'login' => 'ivo@example.test',
            'pwd' => 'secret123',
            'rememberMe' => false,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => '000000'],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('Authenticated', $html);
        $this->assertStringContainsString('Invalid verification code.', $html);
        $this->assertTrue($this->harness->currentUser->isGuest());
        $this->assertNotNull($this->harness->session->get('credentials'));
    }

    public function testConfirmFailsWhenTwoFactorCodeIsMissing(): void
    {
        $user = $this->registerAndConfirmUser('juna', 'juna@example.test', 'secret123');
        $this->enableTwoFactorAuth($user);

        $this->harness->session->set('credentials', [
            'login' => 'juna@example.test',
            'pwd' => 'secret123',
            'rememberMe' => false,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(Method::POST),
        );

        $this->assertStringNotContainsString('Authenticated', $this->harness->responseBody($response));
        $this->assertTrue($this->harness->currentUser->isGuest());
    }

    public function testConfirmFailsWithInvalidEmailTwoFactorCode(): void
    {
        $user = $this->registerAndConfirmUser('quinn', 'quinn@example.test', 'secret123');
        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('email');
        $user->setAuthTfKey('654321');
        $user->save();

        $this->harness->session->set('credentials', [
            'login' => 'quinn@example.test',
            'pwd' => 'secret123',
            'rememberMe' => false,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => '000000'],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('Authenticated', $html);
        $this->assertTrue($this->harness->currentUser->isGuest());
    }

    public function testConfirmHydratesLoginFromRequestBodyOverridingSessionCredentials(): void
    {
        $user = $this->registerAndConfirmUser('theo', 'theo@example.test', 'secret123');

        $this->harness->session->set('credentials', [
            'login' => 'theo@example.test',
            'pwd' => 'secret123',
            'rememberMe' => false,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'nobody@example.test'],
                ),
            ),
        );

        $this->assertStringNotContainsString('Authenticated', $this->harness->responseBody($response));
        $this->assertTrue($this->harness->currentUser->isGuest());
        unset($user);
    }

    public function testConfirmRemembersWhenRememberMeIsAmbiguousTruthyString(): void
    {
        $user = $this->registerAndConfirmUser('rita', 'rita@example.test', 'secret123');
        $code = $this->enableTwoFactorAuth($user);

        $this->harness->session->set('credentials', [
            'login' => 'rita@example.test',
            'pwd' => 'secret123',
            'rememberMe' => 'notabool',
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => $code],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Authenticated');
        $this->assertStringContainsString('autoLogin=', $response->getHeaderLine('Set-Cookie'));
        $this->assertNotNull($this->harness->session->get('__auth_expire'));
    }

    public function testConfirmShowsTranslatedFieldErrorWhenTwoFactorNotConfigured(): void
    {
        $user = $this->registerAndConfirmUser('kara', 'kara@example.test', 'secret123');
        $user->setAuthTfEnabled(true);
        $user->save();

        $this->harness->session->set('credentials', [
            'login' => 'kara@example.test',
            'pwd' => 'secret123',
            'rememberMe' => false,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => '123456'],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('Authenticated', $html);
        $this->assertTrue($this->harness->currentUser->isGuest());
        // Appears twice: once in the error summary, once attached to the
        // twoFactorAuthenticationCode field itself, which only renders when
        // the error's value path actually names that property.
        $this->assertSame(2, substr_count($html, 'Two factor authentication is not configured.'));
    }

    public function testConfirmSucceedsWithValidEmailTwoFactorCode(): void
    {
        $this->registerAndConfirmUser('petra', 'petra@example.test', 'secret123');
        $user = $this->harness->users->findByEmail('petra@example.test');
        $this->assertInstanceOf(User::class, $user);
        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('email');
        $user->setAuthTfKey('654321');
        $user->save();

        $this->harness->session->set('credentials', [
            'login' => 'petra@example.test',
            'pwd' => 'secret123',
            'rememberMe' => false,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => '654321'],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Authenticated');
        $this->assertFalse($this->harness->currentUser->isGuest());
    }

    public function testConfirmSuccessRemovesCredentialsUpdatesMetadataConnectsPendingAccountAndSetsAuthTimeout(): void
    {
        $user = $this->registerAndConfirmUser('nadia', 'nadia@example.test', 'secret123');
        $code = $this->enableTwoFactorAuth($user);

        $pendingAccount = new UserSocialAccount();
        $pendingAccount->setProvider('github');
        $pendingAccount->setClientId('gh-nadia-pending');
        $pendingAccount->setCode('pending-code-nadia');
        $pendingAccount->setCreatedAt(time());
        $pendingAccount->save();

        $this->harness->session->set('social_network_account_code', 'pending-code-nadia');
        $this->harness->session->set('credentials', [
            'login' => 'nadia@example.test',
            'pwd' => 'secret123',
            'rememberMe' => true,
        ]);

        $response = $this->harness->securityController->confirm(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['twoFactorAuthenticationCode' => $code],
                ),
                serverParams: ['REMOTE_ADDR' => $this->remoteAddr],
            ),
        );

        $this->assertResponseContains($response, 'Authenticated');
        $this->assertNull($this->harness->session->get('credentials'));
        $this->assertNotNull($this->harness->session->get('__auth_expire'));
        $this->assertStringContainsString('autoLogin=', $response->getHeaderLine('Set-Cookie'));

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertNotNull($reloaded->getLastLoginAt());
        $this->assertSame($this->remoteAddr, $reloaded->getLastLoginIp());

        $connectedAccount = $this->harness->socialAccounts->findByProviderAndClientId('github', 'gh-nadia-pending');
        $this->assertInstanceOf(UserSocialAccount::class, $connectedAccount);
        $this->assertSame((int) $user->getId(), $connectedAccount->getUserId());
        $this->assertNull($connectedAccount->getCode());
    }

    public function testConnectFallsBackToZeroUserIdWhenIdentityHasNoIdAndRendersAuthenticated(): void
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

        $this->harness->currentUser->overrideIdentity(new NullIdIdentity());

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
            'id' => 'gh-null-id',
            'login' => 'null-id-gh',
            'name' => 'Nully',
            'email' => 'nully@example.test',
        ]);

        $response = $this->harness->securityController->connect(
            $this->harness->request(
                Method::GET,
                queryParams: ['code' => 'oauth-code', 'state' => $state],
            ),
            'github',
        );

        $this->assertResponseContains($response, 'Authenticated');

        $account = $this->harness->socialAccounts->findByProviderAndClientId('github', 'gh-null-id');
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertSame(0, $account->getUserId());
    }

    public function testLoginBlocksUnconfirmedUserWhenEmailConfirmationRequired(): void
    {
        $this->harness->userRegisterService->run([
            'username' => 'pending',
            'email' => 'pending@example.test',
            'password' => 'secret123',
            'gdprConsent' => true,
        ]);

        $response = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'pending@example.test', 'password' => 'secret123'],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertTrue($this->harness->currentUser->isGuest());

        // The error must be attached to the "login" attribute specifically (not just
        // as a form-wide/common error), so it is rendered both in the error summary
        // and next to the login field itself.
        $this->assertSame(2, substr_count($html, 'You need to confirm your email address'));
    }

    public function testLoginFallsBackToLocalhostForEmptyRemoteAddr(): void
    {
        $this->registerAndConfirmUser('yuki', 'yuki@example.test', 'secret123');

        $response = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'yuki@example.test', 'password' => 'secret123'],
                ),
                serverParams: ['REMOTE_ADDR' => ''],
            ),
        );

        $this->assertResponseContains($response, 'Logged in');

        $reloaded = $this->harness->users->findByEmail('yuki@example.test');
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('127.0.0.1', $reloaded->getLastLoginIp());
    }

    public function testLoginIgnoresTwoFactorFlagWhenFeatureDisabled(): void
    {
        $this->assertFalse($this->harness->moduleConfig->enableTwoFactorAuthentication);

        $user = $this->registerAndConfirmUser('twofa', 'twofa@example.test', 'secret123');
        $user->setAuthTfEnabled(true);
        $user->save();

        $response = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'twofa@example.test', 'password' => 'secret123'],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Logged in');
        $this->assertFalse($this->harness->currentUser->isGuest());
    }

    public function testLoginRejectsUnknownLoginAndShowsErrorWithoutCrashing(): void
    {
        $response = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'ghost@example.test', 'password' => 'whatever123'],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertTrue($this->harness->currentUser->isGuest());

        // The error must be attached to the "login" attribute specifically (not just
        // as a form-wide/common error), so it is rendered both in the error summary
        // and next to the login field itself.
        $this->assertSame(2, substr_count($html, 'Invalid login or password'));
    }

    public function testLoginSuccessConnectsPendingSocialAccountAndDispatchesFormEvent(): void
    {
        $user = $this->registerAndConfirmUser('walt', 'walt@example.test', 'secret123');

        $pendingAccount = new UserSocialAccount();
        $pendingAccount->setProvider('github');
        $pendingAccount->setClientId('gh-walt-pending');
        $pendingAccount->setCode('pending-code-walt');
        $pendingAccount->setCreatedAt(time());
        $pendingAccount->save();

        $this->harness->session->set('social_network_account_code', 'pending-code-walt');

        // registerAndConfirmUser() above already dispatches its own FormEvent (from
        // RegistrationController::register()), so we snapshot the count beforehand and
        // require login() to dispatch exactly one more, for this LoginForm instance.
        $formEventsBefore = count(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof FormEvent,
        ));

        $response = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'walt@example.test', 'password' => 'secret123'],
                ),
            ),
        );

        $this->assertResponseContains($response, 'Logged in');

        $connectedAccount = $this->harness->socialAccounts->findByProviderAndClientId('github', 'gh-walt-pending');
        $this->assertInstanceOf(UserSocialAccount::class, $connectedAccount);
        $this->assertSame((int) $user->getId(), $connectedAccount->getUserId());

        $formEvents = array_values(array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof FormEvent,
        ));
        $this->assertCount($formEventsBefore + 1, $formEvents);
        $lastFormEvent = $formEvents[count($formEvents) - 1];
        $this->assertInstanceOf(LoginForm::class, $lastFormEvent->getForm());
        $this->assertSame('walt@example.test', $lastFormEvent->getForm()->login);
    }

    public function testLoginTwoFactorEmailFlowSendsCodeAndRendersEmailHint(): void
    {
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                enableEmailConfirmation: true,
                enableTwoFactorAuthentication: true,
            ),
        );

        $user = $this->registerAndConfirmUser('oona', 'oona@example.test', 'secret123');
        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('email');
        $user->save();

        $messagesBefore = count($this->harness->mailer->messages());

        $response = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'oona@example.test', 'password' => 'secret123'],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Enter the verification code sent to your email', $html);
        $this->assertTrue($this->harness->currentUser->isGuest());
        $this->assertCount($messagesBefore + 1, $this->harness->mailer->messages());

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertNotNull($reloaded->getAuthTfKey());
    }

    public function testLoginTwoFactorFlowStoresExactSessionCredentialsAndRendersConfirmPage(): void
    {
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                enableEmailConfirmation: true,
                enableTwoFactorAuthentication: true,
            ),
        );

        $user = $this->registerAndConfirmUser('vera', 'vera@example.test', 'secret123');
        $user->setAuthTfEnabled(true);
        $user->save();

        $response = $this->harness->securityController->login(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new LoginForm($this->harness->moduleConfig, $this->harness->translator),
                    ['login' => 'vera@example.test', 'password' => 'secret123', 'rememberMe' => true],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Two-Factor Authentication', $html);
        $this->assertStringNotContainsString('Logged in', $html);
        $this->assertTrue($this->harness->currentUser->isGuest());

        $this->assertSame(
            ['login' => 'vera@example.test', 'pwd' => 'secret123', 'rememberMe' => true],
            $this->harness->session->get('credentials'),
        );
    }

    public function testLogoutDoesNotRotateAuthKeyWhenLogoutReturnsFalse(): void
    {
        $user = $this->registerAndConfirmUser('zara', 'zara@example.test', 'secret123');
        $originalAuthKey = $user->getAuthKey();

        // overrideIdentity() makes getIdentity() always return this user (it takes
        // priority over the session-backed identity), which means logout()'s internal
        // switchIdentity() to a guest never becomes observable: isGuest() keeps
        // reporting false, so CurrentUser::logout() returns false even though a
        // User instance is present.
        $this->harness->currentUser->overrideIdentity($user);

        $response = $this->harness->securityController->logout();

        $this->assertResponseContains($response, 'Logged out');

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame($originalAuthKey, $reloaded->getAuthKey());
    }

    public function testLogoutRotatesAuthKeyAndTimestampWhenSuccessful(): void
    {
        $user = $this->registerAndConfirmUser('yosef', 'yosef@example.test', 'secret123');
        $originalAuthKey = $user->getAuthKey();
        $user->setUpdatedAt(1);
        $user->save();

        $this->harness->currentUser->login($user);

        $response = $this->harness->securityController->logout();

        $this->assertResponseContains($response, 'Logged out');

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertNotSame($originalAuthKey, $reloaded->getAuthKey());
        $this->assertGreaterThan(1, $reloaded->getUpdatedAt());
    }

    public function testSocialAuthSuccessAddsRememberMeCookieForAuthenticatedUser(): void
    {
        $oauthHttpClient = new FakeHttpClient();
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                enableEmailConfirmation: true,
                socialNetworkClients: [
                    'github' => [
                        'clientId' => 'github-client-id',
                        'clientSecret' => 'github-client-secret',
                    ],
                ],
            ),
            $oauthHttpClient,
        );

        $user = $this->registerAndConfirmUser('sasha', 'sasha@example.test', 'secret123');
        $socialAccount = new UserSocialAccount();
        $socialAccount->setProvider('github');
        $socialAccount->setClientId('gh-sasha');
        $socialAccount->setUserId((int) $user->getId());
        $socialAccount->setCreatedAt(time());
        $socialAccount->save();

        $redirectResponse = $this->harness->securityController->auth(
            $this->harness->request(Method::GET),
            'github',
        );
        $location = $redirectResponse->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        $this->assertIsString($state);

        $oauthHttpClient->queue('POST', 'https://github.com/login/oauth/access_token', [
            'access_token' => 'oauth-token',
        ]);
        $oauthHttpClient->queue('GET', 'https://api.github.com/user', [
            'id' => 'gh-sasha',
            'login' => 'sasha-gh',
            'name' => 'Sasha Example',
            'email' => 'sasha@example.test',
        ]);

        $response = $this->harness->securityController->auth(
            $this->harness->request(
                Method::GET,
                queryParams: ['code' => 'oauth-code', 'state' => $state],
            ),
            'github',
        );

        $this->assertResponseContains($response, 'Authenticated');
        $this->assertStringContainsString('autoLogin=', $response->getHeaderLine('Set-Cookie'));
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

    private function enableTwoFactorAuth(User $user): string
    {
        $secret = Base32::encode(random_bytes(10));
        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('google');
        $user->setAuthTfKey($secret);
        $user->save();

        $authenticator = new Authenticator(new AuthenticatorOptions());
        $authenticator->setSecret($secret);

        return $authenticator->code();
    }

    private function registerAndConfirmUser(string $username, string $email, string $password): User
    {
        $registerResponse = $this->harness->registrationController->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
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
}

final class NullIdIdentity implements IdentityInterface
{
    public function getId(): ?string
    {
        return null;
    }
}
