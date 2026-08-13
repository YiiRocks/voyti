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
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

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

    public function testConfirm(): void
    {
        // Already confirmed: redirects
        $user1 = $this->createUser('confirmeduser', 'confirmed@example.com');
        $user1->setConfirmedAt(time());
        $user1->save();
        $result = $this->createController()->confirm((int) $user1->getId(), 'code123');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));

        // Successful confirmation: redirects and sets confirmed_at
        $user2 = $this->createUser('unconfirmeduser', 'unconfirmed@example.com');
        $token = new UserToken();
        $token->setUserId((int) $user2->getId());
        $token->setCode(hash('sha256', 'code123'));
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();
        $result = $this->createController()->confirm((int) $user2->getId(), 'code123');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        $this->assertNotNull(User::findById((int) $user2->getId())?->getConfirmedAt());

        // Invalid code: shows error
        $user3 = $this->createUser('unconfirmeduser2', 'unconfirmed2@example.com');
        $html = (string) $this->createController()->confirm((int) $user3->getId(), 'code123')->getBody();
        self::assertStringContainsString('The confirmation link is invalid or expired.', $html);

        // Confirmation disabled: shows error
        $html = (string) $this->createController(VoytiConfigFactory::create(enableEmailConfirmation: false))->confirm(999999, 'code123')->getBody();
        self::assertStringContainsString('Invalid confirmation link', $html);

        // Non-existent user with confirmation enabled: shows error
        $html = (string) $this->createController(VoytiConfigFactory::create(enableEmailConfirmation: true))->confirm(999999, 'code123')->getBody();
        self::assertStringContainsString('Invalid confirmation link', $html);
    }

    public function testConnect(): void
    {
        // Invalid code: shows error
        $html = (string) $this->createController()->connect('code123')->getBody();
        self::assertStringContainsString('Network not found', $html);

        // Valid code: shows form
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('client-1');
        $account->setCode('code123');
        $account->setCreatedAt(time());
        $account->save();
        $html = (string) $this->createController()->connect('code123')->getBody();
        self::assertStringContainsString('Connect account', $html);

        // Valid code with a configured provider client: shows the provider title from the client
        $client = $this->createMock(OAuth2Interface::class);
        $client->method('getName')->willReturn('github');
        $client->method('getTitle')->willReturn('GitHub');
        $collection = new Collection(['github' => $client]);

        $account2 = new UserSocialAccount();
        $account2->setProvider('github');
        $account2->setClientId('client-2');
        $account2->setCode('code456');
        $account2->setCreatedAt(time());
        $account2->save();
        $html = (string) $this->createController(clientCollection: $collection)->connect('code456')->getBody();
        self::assertStringContainsString('GitHub', $html);

        // Valid code with a null client collection (host without yii-auth-client): falls back to the provider key
        $account3 = new UserSocialAccount();
        $account3->setProvider('github');
        $account3->setClientId('client-3');
        $account3->setCode('code789');
        $account3->setCreatedAt(time());
        $account3->save();
        $html = (string) $this->createControllerWithNullCollection()->connect('code789')->getBody();
        self::assertStringContainsString('Connect account', $html);
        self::assertStringContainsString('github', $html);
    }

    public function testRegister(): void
    {
        // GET shows form
        $html = (string) $this->createController()->register(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Create account', $html);

        // POST success: creates user, connects pending social account
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
        $user = User::findByEmail('test@example.com');
        $this->assertNotNull($user);
        $this->assertSame('testuser', $user->getUsername());
        $this->assertTrue(TestPasswordHasherFactory::create()->validate('password123', $user->getPasswordHash()));
        $this->assertTrue($user->isGdprConsent());
        $this->assertSame((int) $user->getId(), UserSocialAccount::findByProviderAndClientId('github', 'client-reg')?->getUserId());

        // POST with service failure: re-renders form with error
        $this->createUser('existing', 'existing@example.com');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'existing2', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);
        $html = (string) $this->createController()->register($request)->getBody();
        self::assertStringContainsString('Create account', $html);
        self::assertStringContainsString('Email already exists', $html);

        // POST with validation errors: re-renders form
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => '', 'email' => '', 'password' => '', 'passwordRepeat' => '']]);
        $html = (string) $this->createControllerWithRealValidation()->register($request)->getBody();
        self::assertStringContainsString('Create account', $html);

        // Disabled config: shows error
        $html = (string) $this->createController(VoytiConfigFactory::create(enableRegistration: false))->register(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Registration is disabled', $html);
    }

    public function testResend(): void
    {
        // GET shows form
        $html = (string) $this->createController()->resend(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Resend confirmation link', $html);

        // POST success: creates token and redirects
        $user = $this->createUser('resenduser', 'test@example.com');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'test@example.com']]);
        $result = $this->createController()->resend($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        $this->assertNotEmpty(UserToken::findByUserId((int) $user->getId()));

        // Disabled config: shows error
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

    private function createController(?VoytiConfig $config = null, ?Collection $clientCollection = null): RegistrationController
    {
        $overrides = [
            ...$this->baseOverrides($config),
            ValidatorInterface::class => $this->validator,
        ];

        if ($clientCollection !== null) {
            $overrides[Collection::class] = $clientCollection;
        }

        return $this->getTestContainer($overrides)->get(RegistrationController::class);
    }

    /**
     * Controller with a null client collection, as a host that has not installed yii-auth-client gets.
     */
    private function createControllerWithNullCollection(?VoytiConfig $config = null): RegistrationController
    {
        return $this->getTestContainer([
            ...$this->baseOverrides($config),
            ValidatorInterface::class => $this->validator,
            RegistrationController::class => [
                'class' => RegistrationController::class,
                '__construct()' => ['clientCollection' => null],
            ],
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
