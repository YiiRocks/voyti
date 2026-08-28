<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Registration;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Auth\PostRegistrationHookInterface;
use YiiRocks\Voyti\Controller\Registration\RegistrationController;
use YiiRocks\Voyti\Event\Auth\BeforeRegisterEvent;
use YiiRocks\Voyti\Event\Auth\BeforeRegisterFormValidationEvent;
use YiiRocks\Voyti\Event\Auth\RegisterFormValidationFailedEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\RecaptchaRegistryTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationControllerTest extends DatabaseTestCase
{
    use RecaptchaRegistryTrait;
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

    public function testRegister(): void
    {
        // GET shows form
        $html = (string) $this->createController()->register(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Create account', $html);

        // POST success: creates user
        $container = $this->getTestContainer([
            ...$this->baseOverrides(VoytiConfigFactory::create()),
            ValidatorInterface::class => $this->validator,
        ]);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123', 'dataProcessingConsent' => '1']]);
        $result = $container->get(RegistrationController::class)->register($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        $user = User::findByEmail('test@example.com');
        $this->assertNotNull($user);
        $this->assertSame('testuser', $user->getUsername());
        $this->assertTrue(TestPasswordHasherFactory::create()->validate('password123', $user->getPasswordHash()));
        $this->assertTrue($user->hasDataProcessingConsent());

        // A successful registration dispatches the form-validation and pre-persist events, in order,
        // with the raw submitted form data and the hydrated (not-yet-saved at dispatch time) user.
        /** @var EventCaptureDispatcher $eventDispatcher */
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        $beforeFormEvent = $eventDispatcher->getEvent(BeforeRegisterFormValidationEvent::class);
        $this->assertInstanceOf(BeforeRegisterFormValidationEvent::class, $beforeFormEvent);
        $this->assertSame('testuser', $beforeFormEvent->getFormData()['register']['username'] ?? null);
        $this->assertSame($request->getServerParams(), $beforeFormEvent->getServerParams());
        $beforeRegisterEvent = $eventDispatcher->getEvent(BeforeRegisterEvent::class);
        $this->assertInstanceOf(BeforeRegisterEvent::class, $beforeRegisterEvent);
        $this->assertSame('testuser', $beforeRegisterEvent->getUser()->getUsername());
        $this->assertSame('test@example.com', $beforeRegisterEvent->getFormData()['email'] ?? null);

        // POST with service failure: re-renders form with error
        $this->createUser('existing', 'existing@example.com');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'existing2', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);
        $html = (string) $this->createController()->register($request)->getBody();
        self::assertStringContainsString('Create account', $html);
        self::assertStringContainsString('Email already exists', $html);

        // POST with validation errors: re-renders form and dispatches RegisterFormValidationFailedEvent
        $this->configureRecaptchaRegistry();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => '', 'email' => '', 'password' => '', 'passwordRepeat' => '']]);
        $realValidationContainer = $this->getTestContainer($this->baseOverrides());
        $html = (string) $realValidationContainer->get(RegistrationController::class)->register($request)->getBody();
        self::assertStringContainsString('Create account', $html);
        /** @var EventCaptureDispatcher $realValidationEventDispatcher */
        $realValidationEventDispatcher = $realValidationContainer->get(EventDispatcherInterface::class);
        $validationFailedEvent = $realValidationEventDispatcher->getEvent(RegisterFormValidationFailedEvent::class);
        $this->assertInstanceOf(RegisterFormValidationFailedEvent::class, $validationFailedEvent);
        $this->assertNotEmpty($validationFailedEvent->getErrors());
        $this->assertSame($request->getParsedBody(), $validationFailedEvent->getFormData());
        $this->assertSame($request->getServerParams(), $validationFailedEvent->getServerParams());
        $this->assertFalse($realValidationEventDispatcher->hasEvent(BeforeRegisterEvent::class));

        // Disabled config: shows error
        $html = (string) $this->createController(VoytiConfigFactory::create(enableRegistration: false))->register(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Registration is disabled', $html);
    }

    public function testRegisterConsultsPostRegistrationHooks(): void
    {
        // Packages such as yiirocks/voyti-social-auth hook into a successful registration via this
        // seam (e.g. connecting a pending social account); core only needs to prove every registered
        // hook is invoked with the newly registered user.
        $hook = new class implements PostRegistrationHookInterface {
            /** @var list<string|null> */
            public array $calledWith = [];

            public function handle(User $user): void
            {
                $this->calledWith[] = $user->getId();
            }
        };

        $container = $this->getTestContainer([
            ...$this->baseOverrides(VoytiConfigFactory::create()),
            ValidatorInterface::class => $this->validator,
            RegistrationController::class => [
                'class' => RegistrationController::class,
                '__construct()' => ['postRegistrationHooks' => [$hook]],
            ],
        ]);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'hookuser', 'email' => 'hookuser@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);
        $container->get(RegistrationController::class)->register($request);

        $user = User::findByEmail('hookuser@example.com');
        $this->assertNotNull($user);
        $this->assertSame([$user->getId()], $hook->calledWith);
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

    private function createController(?VoytiConfig $config = null): RegistrationController
    {
        $overrides = [
            ...$this->baseOverrides($config),
            ValidatorInterface::class => $this->validator,
        ];

        return $this->getTestContainer($overrides)->get(RegistrationController::class);
    }
}
