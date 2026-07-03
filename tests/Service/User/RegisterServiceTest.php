<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;

final class RegisterServiceTest extends TestCase
{
    private bool $hadRemoteAddress = false;
    private string $remoteAddress = '';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->hadRemoteAddress = array_key_exists('REMOTE_ADDR', $_SERVER);
        $this->remoteAddress = $this->hadRemoteAddress ? (string) $_SERVER['REMOTE_ADDR'] : '';

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            unconfirmed_email VARCHAR(255),
            registration_ip VARCHAR(45),
            flags INTEGER NOT NULL DEFAULT 0,
            confirmed_at INTEGER,
            blocked_at INTEGER,
            updated_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            last_login_at INTEGER,
            auth_tf_key VARCHAR(64),
            auth_tf_enabled INTEGER DEFAULT 0,
            password_changed_at INTEGER,
            last_login_ip VARCHAR(45),
            gdpr_deleted INTEGER DEFAULT 0,
            gdpr_consent INTEGER DEFAULT 0,
            gdpr_consent_date INTEGER,
            auth_tf_type VARCHAR(20)
        )')->execute();
        $db->createCommand('CREATE TABLE {{%user_profile}} (
            user_id INTEGER NOT NULL PRIMARY KEY,
            name VARCHAR(255),
            public_email VARCHAR(255),
            gravatar_email VARCHAR(255),
            location VARCHAR(255),
            website VARCHAR(255),
            bio TEXT,
            timezone VARCHAR(40)
        )')->execute();
        $db->createCommand('CREATE TABLE {{%user_token}} (
            user_id INTEGER NOT NULL,
            code VARCHAR(32) NOT NULL,
            type SMALLINT NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, code, type)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hadRemoteAddress) {
            $_SERVER['REMOTE_ADDR'] = $this->remoteAddress;
        } else {
            unset($_SERVER['REMOTE_ADDR']);
        }

        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testRunCastsGdprConsentValueToBool(): void
    {
        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
                'enableGdprCompliance' => true,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $result = $service->run([
            'username' => 'erin',
            'email' => 'erin@example.com',
            'password' => 'erin-secret',
            'gdprConsent' => '1',
        ]);

        self::assertTrue($result->isSuccess());
        $user = User::query()->findByPk(1);
        self::assertTrue($user->isGdprConsent());
        self::assertNotNull($user->getGdprConsentDate());
    }

    public function testRunDefaultsGdprConsentToFalseWhenMissing(): void
    {
        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
                'enableGdprCompliance' => true,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $result = $service->run([
            'username' => 'dana',
            'email' => 'dana@example.com',
            'password' => 'dana-secret',
        ]);

        self::assertTrue($result->isSuccess());
        $user = User::query()->findByPk(1);
        self::assertFalse($user->isGdprConsent());
        self::assertNull($user->getGdprConsentDate());
    }

    public function testRunGeneratesRandomPasswordWhenPasswordIsEmptyString(): void
    {
        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $passwordGenerator = new RecordingPasswordGenerator();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
            $passwordGenerator,
        );

        $result = $service->run([
            'username' => 'dave',
            'email' => 'dave@example.com',
            'password' => '',
            'gdprConsent' => false,
        ]);

        self::assertTrue($result->isSuccess());
        self::assertSame([12], $passwordGenerator->requestedLengths);
        $user = User::query()->findByPk(1);
        self::assertFalse(password_verify('', $user->getPasswordHash()));
        self::assertTrue($hasher->validate(str_repeat('x', 12), $user->getPasswordHash()));
    }

    public function testRunGeneratesRandomPasswordWhenPasswordIsNotAString(): void
    {
        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $result = $service->run([
            'username' => 'carl',
            'email' => 'carl@example.com',
            'password' => 123,
            'gdprConsent' => false,
        ]);

        self::assertTrue($result->isSuccess());
        $user = User::query()->findByPk(1);
        self::assertFalse($hasher->validate('123', $user->getPasswordHash()));
    }

    public function testRunNormalizesNonStringUsernameAndEmail(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $result = $service->run([
            'username' => 123,
            'email' => new \stdClass(),
            'password' => 'typed-password',
            'gdprConsent' => false,
        ]);

        self::assertTrue($result->isSuccess());
        $user = User::query()->findByPk(1);
        self::assertSame('', $user->getUsername());
        self::assertSame('', $user->getEmail());
        self::assertSame('127.0.0.1', $user->getRegistrationIp());
        self::assertTrue($hasher->validate('typed-password', $user->getPasswordHash()));
        self::assertCount(1, $mailer->messages());
        self::assertSame('', $mailer->messages()[0]->getTo());
    }

    public function testRunSavesConfirmedUserAndSendsWelcomeWithoutConfirmation(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.99';

        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
                'disableIpLogging' => true,
                'enableGdprCompliance' => false,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $before = time();
        $result = $service->run([
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => 'bob-secret',
            'gdprConsent' => true,
        ]);
        $after = time();

        self::assertTrue($result->isSuccess());
        self::assertSame('voyti.registration.account_created', $result->getMessage());

        $user = User::query()->findByPk(1);
        self::assertSame('bob', $user->getUsername());
        self::assertSame('bob@example.com', $user->getEmail());
        self::assertTrue($hasher->validate('bob-secret', $user->getPasswordHash()));
        self::assertSame('127.0.0.1', $user->getRegistrationIp());
        self::assertTrue($user->isConfirmed());
        self::assertFalse($user->isGdprConsent());
        self::assertNull($user->getGdprConsentDate());
        self::assertGreaterThanOrEqual($before, $user->getConfirmedAt());
        self::assertLessThanOrEqual($after, $user->getConfirmedAt());
        self::assertCount(0, UserToken::query()->all());

        $profile = $user->getProfile();
        self::assertSame(1, $profile->getUserId());

        $messages = $mailer->messages();
        self::assertCount(1, $messages);
        self::assertSame('bob@example.com', $messages[0]->getTo());
        self::assertStringContainsString('bob', (string) $messages[0]->getHtmlBody());
        self::assertStringNotContainsString('registration-confirm', (string) $messages[0]->getTextBody());

        $events = $dispatcher->events();
        self::assertCount(2, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(AfterRegisterEvent::class, $events[1]);
        self::assertSame('bob@example.com', $events[0]->getUser()->getEmail());
        self::assertSame('bob@example.com', $events[1]->getUser()->getEmail());
    }

    public function testRunSavesPendingUserAndConfirmationToken(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';

        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => true,
                'enableGdprCompliance' => true,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $before = time();
        $result = $service->run([
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => 'correct horse battery staple',
            'gdprConsent' => true,
        ]);
        $after = time();

        self::assertTrue($result->isSuccess());
        self::assertSame('voyti.registration.account_created_check_email', $result->getMessage());

        $user = User::query()->findByPk(1);
        self::assertSame('alice', $user->getUsername());
        self::assertSame('alice@example.com', $user->getEmail());
        self::assertTrue($hasher->validate('correct horse battery staple', $user->getPasswordHash()));
        self::assertNotSame('', $user->getAuthKey());
        self::assertSame('203.0.113.42', $user->getRegistrationIp());
        self::assertFalse($user->isConfirmed());
        self::assertTrue($user->isGdprConsent());
        self::assertGreaterThanOrEqual($before, $user->getCreatedAt());
        self::assertLessThanOrEqual($after, $user->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $user->getUpdatedAt());
        self::assertLessThanOrEqual($after, $user->getUpdatedAt());
        self::assertGreaterThanOrEqual($before, $user->getGdprConsentDate());
        self::assertLessThanOrEqual($after, $user->getGdprConsentDate());

        $profile = $user->getProfile();
        self::assertSame(1, $profile->getUserId());

        $tokens = UserToken::query()->all();
        self::assertCount(1, $tokens);
        self::assertSame(1, $tokens[0]->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRMATION, $tokens[0]->getType());
        self::assertSame(32, strlen($tokens[0]->getCode()));
        self::assertGreaterThanOrEqual($before, $tokens[0]->getCreatedAt());
        self::assertLessThanOrEqual($after, $tokens[0]->getCreatedAt());

        $messages = $mailer->messages();
        self::assertCount(1, $messages);
        self::assertSame('alice@example.com', $messages[0]->getTo());
        self::assertStringContainsString('alice', (string) $messages[0]->getHtmlBody());
        self::assertStringContainsString(
            'https://example.test/voyti/registration-confirm?id=1&code=' . $tokens[0]->getCode(),
            (string) $messages[0]->getTextBody(),
        );

        $events = $dispatcher->events();
        self::assertCount(2, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(AfterRegisterEvent::class, $events[1]);
        self::assertSame('alice@example.com', $events[0]->getUser()->getEmail());
        self::assertSame('alice@example.com', $events[1]->getUser()->getEmail());
    }

    public function testRunUsesProvidedPasswordWithoutGeneratingOne(): void
    {
        $dispatcher = new RegisterEventCollector();
        $mailer = new MailCapture();
        $hasher = $this->createHasher();
        $passwordGenerator = new RecordingPasswordGenerator();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
            $passwordGenerator,
        );

        $result = $service->run([
            'username' => 'faye',
            'email' => 'faye@example.com',
            'password' => 'faye-secret',
            'gdprConsent' => false,
        ]);

        self::assertTrue($result->isSuccess());
        self::assertSame([], $passwordGenerator->requestedLengths);
    }

    private function createHasher(): PasswordHasher
    {
        return new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }

    private function createService(
        ModuleConfig $config,
        RegisterEventCollector $dispatcher,
        MailCapture $mailer,
        PasswordHasher $hasher,
        ?PasswordGeneratorInterface $passwordGenerator = null,
    ): RegisterService {
        $mailService = new MailService(
            $mailer,
            $config->mailPath,
            $this->getTranslator(),
            new TestUrlGenerator(),
            $config->appName,
        );

        return new RegisterService(
            new UserRepository(),
            $mailService,
            $dispatcher,
            $hasher,
            $config,
            $passwordGenerator ?? new RandomPasswordGenerator(),
        );
    }
}

final class RegisterEventCollector implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $events = [];

    #[\Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        return $event;
    }

    /**
     * @return list<object>
     */
    public function events(): array
    {
        return $this->events;
    }
}

final class MailCapture implements MailerInterface
{
    /** @var list<MessageInterface> */
    private array $messages = [];

    /**
     * @return list<MessageInterface>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    #[\Override]
    public function send(MessageInterface $message): void
    {
        $this->messages[] = $message;
    }

    #[\Override]
    public function sendMultiple(array $messages): SendResults
    {
        foreach ($messages as $message) {
            $this->send($message);
        }

        return new SendResults($this->messages, []);
    }
}

final class TestUrlGenerator implements UrlGeneratorInterface
{
    private string $uriPrefix = '';

    #[\Override]
    public function generate(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null): string
    {
        return $this->buildUrl('/' . $name, $arguments, $queryParameters, $hash);
    }

    #[\Override]
    public function generateAbsolute(
        string $name,
        array $arguments = [],
        array $queryParameters = [],
        ?string $hash = null,
        ?string $scheme = null,
        ?string $host = null
    ): string {
        return 'https://example.test' . $this->generate($name, $arguments, $queryParameters, $hash);
    }

    #[\Override]
    public function generateFromCurrent(
        array $replacedArguments,
        array $queryParameters = [],
        ?string $hash = null,
        ?string $fallbackRouteName = null
    ): string {
        return $this->buildUrl('/current', $replacedArguments, $queryParameters, $hash);
    }

    #[\Override]
    public function getUriPrefix(): string
    {
        return $this->uriPrefix;
    }

    #[\Override]
    public function setDefaultArgument(string $name, \Stringable|bool|float|int|string|null $value): void
    {
    }

    #[\Override]
    public function setUriPrefix(string $name): void
    {
        $this->uriPrefix = $name;
    }

    private function buildUrl(string $path, array $arguments, array $queryParameters, ?string $hash): string
    {
        $query = array_merge($arguments, $queryParameters);
        $suffix = $query === [] ? '' : '?' . http_build_query($query);
        $fragment = $hash === null ? '' : '#' . $hash;
        return $this->uriPrefix . $path . $suffix . $fragment;
    }
}

final class RecordingPasswordGenerator implements PasswordGeneratorInterface
{
    /** @var list<int> */
    public array $requestedLengths = [];

    public function generate(int $length): string
    {
        $this->requestedLengths[] = $length;

        return str_repeat('x', $length);
    }
}
