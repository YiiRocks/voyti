<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\User\FormEvent;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;
use Yiisoft\Security\Random;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidationContext;
use Yiisoft\Validator\ValidatorInterface;

final class RegistrationControllerTest extends TestCase
{
    private ?ConnectionInterface $db = null;
    private ControllerHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getDb();
        ConnectionProvider::set($this->db);
        $this->createSchema($this->db);

        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';
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

    public function testConfirmReturnsInvalidLinkErrorWhenEmailConfirmationDisabledEvenForExistingUser(): void
    {
        $this->harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new ModuleConfig(
                enableRegistration: true,
                enableEmailConfirmation: false,
            ),
        );

        $user = new User();
        $user->setUsername('quinn');
        $user->setEmail('quinn@example.test');
        $user->setPasswordHash('hash');
        $user->setAuthKey(Random::string());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $response = $this->harness->registrationController->confirm(
            $this->harness->request(Method::GET),
            (int) $user->getId(),
            'irrelevant-code',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Invalid confirmation link', $this->harness->responseBody($response));
        $this->assertStringNotContainsString(
            'The confirmation link is invalid or expired.',
            $this->harness->responseBody($response),
        );
    }

    public function testRegisterFormRerendersWithValidationErrorsWhenSubmissionIsInvalid(): void
    {
        $controller = $this->buildControllerWithValidator(
            new RejectingValidatorStub('This value is not acceptable.'),
        );

        $response = $controller->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'username' => 'quincy',
                        'email' => 'quincy@example.test',
                        'password' => 'secret123',
                        'gdprConsent' => true,
                    ],
                ),
            ),
        );

        $html = $this->harness->responseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('This value is not acceptable.', $html);
    }

    public function testRegisterPassesGdprConsentToRegisterService(): void
    {
        $response = $this->harness->registrationController->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'username' => 'rachel',
                        'email' => 'rachel@example.test',
                        'password' => 'secret123',
                        'gdprConsent' => true,
                    ],
                ),
            ),
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/login', $response->getHeaderLine('Location'));

        $user = $this->harness->users->findByEmail('rachel@example.test');
        $this->assertTrue($user->isGdprConsent());
        $this->assertNotNull($user->getGdprConsentDate());
    }

    public function testRegisterPassesSubmittedUsernameToRegisterService(): void
    {
        $response = $this->harness->registrationController->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'username' => 'quentin',
                        'email' => 'quentin@example.test',
                        'password' => 'secret123',
                        'gdprConsent' => true,
                    ],
                ),
            ),
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/login', $response->getHeaderLine('Location'));

        $user = $this->harness->users->findByEmail('quentin@example.test');
        $this->assertSame('quentin', $user->getUsername());
    }

    public function testSuccessfulRegistrationDispatchesFormEvent(): void
    {
        $response = $this->harness->registrationController->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'username' => 'sofia',
                        'email' => 'sofia@example.test',
                        'password' => 'secret123',
                        'gdprConsent' => true,
                    ],
                ),
            ),
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/login', $response->getHeaderLine('Location'));
        $this->assertSame(
            'Account created. Check your email for the confirmation link.',
            $this->harness->flash->get('success'),
        );

        $formEvents = array_filter(
            $this->harness->eventDispatcher->events(),
            static fn (object $event): bool => $event instanceof FormEvent,
        );
        $this->assertNotEmpty($formEvents);
        $dispatchedForm = reset($formEvents);
        $this->assertInstanceOf(FormEvent::class, $dispatchedForm);
        $this->assertInstanceOf(RegistrationForm::class, $dispatchedForm->getForm());
    }

    /**
     * Builds a RegistrationController wired to the given validator, instead of the harness's
     * always-valid stub, so a chosen validation outcome can be exercised.
     */
    private function buildControllerWithValidator(
        ValidatorInterface $validator,
    ): \YiiRocks\Voyti\Controller\RegistrationController {
        $pendingSocialAccountService = new PendingSocialAccountService(
            $this->harness->socialAccounts,
            $this->harness->session,
        );

        return new \YiiRocks\Voyti\Controller\RegistrationController(
            $this->harness->translator,
            $this->harness->webViewRenderer,
            $this->harness->userRegisterService,
            $this->harness->users,
            $this->harness->userTokens,
            $this->harness->userConfirmationService,
            $this->harness->accountConfirmationService,
            $this->harness->resendConfirmationService,
            $validator,
            $this->harness->eventDispatcher,
            $this->harness->url,
            $this->harness->moduleConfig,
            $pendingSocialAccountService,
            $this->harness->hydrator,
            new \Nyholm\Psr7\Factory\Psr17Factory(),
            $this->harness->flash,
        );
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
}

/**
 * A validator stub that always returns an invalid Result without itself notifying the
 * data object of the outcome, unlike Yiisoft\Validator\Validator (which, for data objects
 * implementing PostValidationHookInterface, calls processValidationResult() automatically).
 * This isolates and exercises the controller's own explicit processValidationResult() call.
 */
final class RejectingValidatorStub implements ValidatorInterface
{
    public function __construct(private readonly string $message)
    {
    }

    public function validate(
        mixed $data,
        callable|iterable|object|string|null $rules = null,
        ?ValidationContext $context = null,
    ): Result {
        $result = new Result();
        $result->addError($this->message);

        return $result;
    }
}
