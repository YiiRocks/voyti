<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use YiiRocks\Voyti\Controller\RecoveryController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidationContext;
use Yiisoft\Validator\ValidatorInterface;

final class RecoveryControllerTest extends TestCase
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

    public function testRequestActionDisabledWhenBothRecoveryFlagsFirstCombination(): void
    {
        $harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new \YiiRocks\Voyti\ModuleConfig(
                enableRegistration: true,
                enableEmailConfirmation: true,
                enableGdprCompliance: true,
                allowAccountDelete: true,
                allowPasswordRecovery: true,
                allowAdminPasswordRecovery: false,
                emailChangeStrategy: 1,
            ),
        );

        $response = $harness->recoveryController->reset(
            $harness->request(Method::GET),
            1,
            'irrelevant-code',
        );

        $body = $harness->responseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Password reset is disabled', $body);
        $this->assertStringContainsString('Recovery link is invalid or expired', $body);
    }

    public function testRequestActionDisabledWhenBothRecoveryFlagsSecondCombination(): void
    {
        $harness = new ControllerHarness(
            dirname(__DIR__, 2),
            new \YiiRocks\Voyti\ModuleConfig(
                enableRegistration: true,
                enableEmailConfirmation: true,
                enableGdprCompliance: true,
                allowAccountDelete: true,
                allowPasswordRecovery: false,
                allowAdminPasswordRecovery: true,
                emailChangeStrategy: 1,
            ),
        );

        $response = $harness->recoveryController->reset(
            $harness->request(Method::GET),
            1,
            'irrelevant-code',
        );

        $body = $harness->responseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Password reset is disabled', $body);
        $this->assertStringContainsString('Recovery link is invalid or expired', $body);
    }

    public function testRequestActionHydratesNonArrayParsedBody(): void
    {
        $request = (new ServerRequest(Method::POST, 'https://example.test/'))
            ->withParsedBody((object) ['email' => 'nobody@example.test']);

        $response = $this->harness->recoveryController->request($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'If the email exists, a recovery message has been sent',
            $this->harness->responseBody($response),
        );
    }

    public function testRequestActionShowsValidationErrorsWhenResultIsProcessed(): void
    {
        // This fake validator returns an invalid result but does not implement
        // PostValidationHookInterface / call processValidationResult() itself,
        // unlike the real Validator (which would call it internally and make
        // the controller's own explicit call an equivalent mutant). This way
        // only the controller's explicit `$form->processValidationResult($result)`
        // call is responsible for making the errors observable in the view.
        $controller = $this->buildControllerWithValidator(new NonHookingInvalidValidator());

        $response = $controller->request(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RecoveryForm($this->harness->moduleConfig, $this->harness->translator, RecoveryForm::SCENARIO_REQUEST),
                    ['email' => 'not-an-email'],
                ),
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'Custom fake validation error',
            $this->harness->responseBody($response),
        );
    }

    public function testResetActionHydratesNonArrayParsedBody(): void
    {
        $user = $this->registerUser('mia', 'mia@example.test', 'secret123');
        $token = $this->createRecoveryToken((int) $user->getId());

        $request = (new ServerRequest(Method::POST, 'https://example.test/'))
            ->withParsedBody((object) ['password' => 'new-secret123']);

        $response = $this->harness->recoveryController->reset(
            $request,
            (int) $user->getId(),
            $token->getCode(),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'Password has been changed',
            $this->harness->responseBody($response),
        );

        $reloaded = $this->harness->users->findById((int) $user->getId());
        $this->assertTrue((new PasswordHasher())->validate('new-secret123', $reloaded->getPasswordHash()));
    }

    public function testResetActionMissingTokenReturnsLinkInvalidMessage(): void
    {
        $response = $this->harness->recoveryController->reset(
            $this->harness->request(Method::GET),
            999,
            'no-such-code',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'Recovery link is invalid or expired',
            $this->harness->responseBody($response),
        );
    }

    public function testResetActionSuccessRendersPasswordChangedMessageWithTranslatorContext(): void
    {
        $user = $this->registerUser('nina', 'nina@example.test', 'secret123');
        $token = $this->createRecoveryToken((int) $user->getId());

        $response = $this->harness->recoveryController->reset(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new RecoveryForm($this->harness->moduleConfig, $this->harness->translator, RecoveryForm::SCENARIO_RESET),
                    ['password' => 'another-secret123'],
                ),
            ),
            (int) $user->getId(),
            $token->getCode(),
        );

        $body = $this->harness->responseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Password has been changed', $body);
        // Rendered via translator injected in the message view; proves the
        // 'translator' array key survived (ArrayItem mutant breaks this key).
        $this->assertStringContainsString('Go home', $body);
    }

    public function testResetActionTokenWithMissingUserReturnsLinkInvalidMessage(): void
    {
        $orphanUserId = 424_242;
        $token = $this->createRecoveryToken($orphanUserId);

        $response = $this->harness->recoveryController->reset(
            $this->harness->request(Method::GET),
            $orphanUserId,
            $token->getCode(),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'Recovery link is invalid or expired',
            $this->harness->responseBody($response),
        );
    }

    private function buildControllerWithValidator(\Yiisoft\Validator\ValidatorInterface $validator): RecoveryController
    {
        return new RecoveryController(
            $this->harness->translator,
            $this->harness->webViewRenderer,
            $this->harness->url,
            new RecoveryService(
                $this->harness->users,
                $this->harness->userTokenFactory,
                $this->harness->mailService,
                $this->harness->moduleConfig,
                $this->harness->translator,
                $this->harness->eventDispatcher,
            ),
            $this->harness->resetPasswordService,
            $this->harness->users,
            $this->harness->userTokens,
            $validator,
            $this->harness->eventDispatcher,
            $this->harness->moduleConfig,
            $this->harness->hydrator,
        );
    }

    private function createRecoveryToken(int $userId): UserToken
    {
        $token = new UserToken();
        $token->setUserId($userId);
        $token->setType(UserToken::TYPE_RECOVERY);
        $token->setCreatedAt(time());
        $token->setCode(Random::string(32));
        $token->save();

        return $token;
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

    private function registerUser(string $username, string $email, string $password): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash((new PasswordHasher())->hash($password));
        $user->setAuthKey(Random::string(32));
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setConfirmedAt(time());
        $user->save();

        return $user;
    }
}

/**
 * A validator stub that returns an invalid {@see Result} without triggering
 * {@see \Yiisoft\Validator\PostValidationHookInterface} itself (unlike the real
 * Yiisoft Validator, which invokes `processValidationResult()` on the data
 * being validated automatically). This isolates the controller's own explicit
 * call to `$form->processValidationResult($result)`.
 */
final class NonHookingInvalidValidator implements ValidatorInterface
{
    #[\Override]
    public function validate(
        mixed $data,
        callable|iterable|object|string|null $rules = null,
        ?ValidationContext $context = null,
    ): Result {
        $result = new Result();
        $result->addError('Custom fake validation error', valuePath: ['email']);

        return $result;
    }
}
