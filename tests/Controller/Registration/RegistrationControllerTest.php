<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Registration;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use YiiRocks\Voyti\Controller\Registration\RegistrationController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationControllerTest extends DatabaseTestCase
{
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private FlashInterface&MockObject $flash;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->validator = $this->mockValidValidator();
    }

    public function testConfirmAlreadyConfirmedUser(): void
    {
        $user = $this->createUser('confirmeduser', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        $result = $this->createController()->confirm((int) $user->getId(), 'code123');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
    }

    public function testConfirmSuccessful(): void
    {
        $user = $this->createUser('unconfirmeduser', 'unconfirmed@example.com');
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode(hash('sha256', 'code123'));
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $result = $this->createController()->confirm((int) $user->getId(), 'code123');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        $this->assertNotNull(User::findById((int) $user->getId())?->getConfirmedAt());
    }

    public function testConfirmWithInvalidCodeShowsError(): void
    {
        $user = $this->createUser('unconfirmeduser2', 'unconfirmed2@example.com');

        $html = (string) $this->createController()->confirm((int) $user->getId(), 'code123')->getBody();

        self::assertStringContainsString('The confirmation link is invalid or expired.', $html);
    }

    public function testConfirmWithInvalidUserOrDisabledConfig(): void
    {
        $html = (string) $this->createController(VoytiConfigFactory::create(enableEmailConfirmation: false))->confirm(999999, 'code123')->getBody();

        self::assertStringContainsString('Invalid confirmation link', $html);
    }

    public function testConfirmWithNonExistentUserWhenConfirmationEnabled(): void
    {
        // Confirmation is enabled but the user does not exist: the invalid-link error must render
        // (rather than proceeding to operate on a null user).
        $html = (string) $this->createController(VoytiConfigFactory::create(enableEmailConfirmation: true))->confirm(999999, 'code123')->getBody();

        self::assertStringContainsString('Invalid confirmation link', $html);
    }

    public function testConnectWithInvalidCodeShowsError(): void
    {
        // No pending social account exists for the code, so useCode() returns null.
        $html = (string) $this->createController()->connect('code123')->getBody();

        self::assertStringContainsString('Network not found', $html);
    }

    public function testConnectWithValidCodeShowsForm(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('client-1');
        $account->setCode('code123');
        $account->setCreatedAt(time());
        $account->save();

        $html = (string) $this->createController()->connect('code123')->getBody();

        self::assertStringContainsString('Connect account', $html);
    }

    public function testRegisterGetShowsForm(): void
    {
        $html = (string) $this->createController()->register(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Create account', $html);
    }

    public function testRegisterPostSuccessful(): void
    {
        // A pending social account is waiting to be linked to the new user.
        $pending = new UserSocialAccount();
        $pending->setProvider('github');
        $pending->setClientId('client-reg');
        $pending->setCode('regpend');
        $pending->setCreatedAt(time());
        $pending->save();

        $container = $this->getTestContainer([
            ...$this->baseOverrides(VoytiConfigFactory::create(enableGdprCompliance: true)),
            ValidatorInterface::class => $this->validator,
        ]);
        $container->get(SessionInterface::class)->set('social_network_account_code', 'regpend');

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123', 'gdprConsent' => '1']]);

        $result = $container->get(RegistrationController::class)->register($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        // The real RegisterService created the account with the submitted username/password/consent.
        $user = User::findByEmail('test@example.com');
        $this->assertNotNull($user);
        $this->assertSame('testuser', $user->getUsername());
        $this->assertTrue(TestPasswordHasherFactory::create()->validate('password123', $user->getPasswordHash()));
        $this->assertTrue($user->isGdprConsent());
        // The pending social account was connected to the new user.
        $this->assertSame((int) $user->getId(), UserSocialAccount::findByProviderAndClientId('github', 'client-reg')?->getUserId());
    }

    public function testRegisterPostWithServiceFailure(): void
    {
        // A user with this email already exists, so the real RegisterService returns a failure.
        $this->createUser('existing', 'existing@example.com');

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'existing2', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);

        // On failure the register form is re-rendered with the service's error message.
        $html = (string) $this->createController()->register($request)->getBody();

        self::assertStringContainsString('Create account', $html);
        self::assertStringContainsString('Email already exists', $html);
    }

    public function testRegisterPostWithValidationErrors(): void
    {
        // Needs the real Validator - this test's whole point is that empty required fields get rejected.
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => '', 'email' => '', 'password' => '', 'passwordRepeat' => '']]);

        // Invalid input re-renders the register form rather than redirecting.
        $html = (string) $this->createControllerWithRealValidation()->register($request)->getBody();

        // A rendered form (rather than an empty redirect body) confirms the invalid input was rejected.
        self::assertStringContainsString('Create account', $html);
    }

    public function testRegisterWhenDisabledShowsError(): void
    {
        $html = (string) $this->createController(VoytiConfigFactory::create(enableRegistration: false))->register(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Registration is disabled', $html);
    }

    public function testResendGetShowsForm(): void
    {
        $html = (string) $this->createController()->resend(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Resend confirmation link', $html);
    }

    public function testResendPostSuccessful(): void
    {
        $user = $this->createUser('resenduser', 'test@example.com');

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'test@example.com']]);

        $result = $this->createController()->resend($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        // The real ConfirmationService issued a fresh confirmation token.
        $this->assertNotEmpty(UserToken::findByUserId((int) $user->getId()));
    }

    public function testResendWhenDisabledShowsError(): void
    {
        $html = (string) $this->createController(VoytiConfigFactory::create(enableEmailConfirmation: false))->resend(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Email confirmation is disabled', $html);
    }

    private function baseOverrides(?VoytiConfig $config = null): array
    {
        $overrides = [
            FlashInterface::class => $this->flash,
        ];

        if ($config !== null) {
            $overrides[VoytiConfig::class] = $config;
        }

        return $overrides;
    }

    private function createController(?VoytiConfig $config = null): RegistrationController
    {
        return $this->getTestContainer([
            ...$this->baseOverrides($config),
            ValidatorInterface::class => $this->validator,
        ])->get(RegistrationController::class);
    }

    /**
     * Uses the real ValidatorInterface instead of the fast valid-by-default mock, for tests whose point is that a
     * real validation rule rejects the input.
     */
    private function createControllerWithRealValidation(?VoytiConfig $config = null): RegistrationController
    {
        return $this->getTestContainer($this->baseOverrides($config))->get(RegistrationController::class);
    }
}
