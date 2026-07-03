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
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;

final class CreateServiceTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

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
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testRunCreatesConfirmedUserAndSendsWelcomeWithoutConfirmation(): void
    {
        $dispatcher = new CreateEventCollector();
        $mailer = new CreateMailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => false,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $before = time();
        $result = $service->run('bob@example.com', 'bob', 'bob-secret');
        $after = time();

        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());

        $user = User::query()->findByPk(1);
        self::assertSame('bob', $user->getUsername());
        self::assertSame('bob@example.com', $user->getEmail());
        self::assertTrue($hasher->validate('bob-secret', $user->getPasswordHash()));
        self::assertNotSame('', $user->getAuthKey());
        self::assertTrue($user->isConfirmed());
        self::assertGreaterThanOrEqual($before, $user->getConfirmedAt());
        self::assertLessThanOrEqual($after, $user->getConfirmedAt());
        self::assertCount(0, UserToken::query()->all());

        $profile = $user->getProfile();
        self::assertSame(1, $profile->getUserId());

        $messages = $mailer->messages();
        self::assertCount(1, $messages);
        self::assertSame('bob@example.com', $messages[0]->getTo());
        self::assertStringNotContainsString('registration-confirm', (string) $messages[0]->getTextBody());

        $events = $dispatcher->events();
        self::assertCount(2, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(AfterRegisterEvent::class, $events[1]);
        self::assertSame('bob@example.com', $events[0]->getUser()->getEmail());
        self::assertSame('bob@example.com', $events[1]->getUser()->getEmail());
    }

    public function testRunCreatesPendingUserWithConfirmationTokenAndDispatchesEvents(): void
    {
        $dispatcher = new CreateEventCollector();
        $mailer = new CreateMailCapture();
        $hasher = $this->createHasher();
        $service = $this->createService(
            ModuleConfig::fromArray([
                'enableEmailConfirmation' => true,
            ]),
            $dispatcher,
            $mailer,
            $hasher,
        );

        $before = time();
        $result = $service->run('alice@example.com', 'alice', 'correct horse battery staple');
        $after = time();

        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());

        $user = User::query()->findByPk(1);
        self::assertSame('alice', $user->getUsername());
        self::assertSame('alice@example.com', $user->getEmail());
        self::assertTrue($hasher->validate('correct horse battery staple', $user->getPasswordHash()));
        self::assertNotSame('', $user->getAuthKey());
        self::assertGreaterThanOrEqual($before, $user->getCreatedAt());
        self::assertLessThanOrEqual($after, $user->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $user->getUpdatedAt());
        self::assertLessThanOrEqual($after, $user->getUpdatedAt());
        self::assertFalse($user->isConfirmed());

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

    private function createHasher(): PasswordHasher
    {
        return new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }

    private function createService(
        ModuleConfig $config,
        CreateEventCollector $dispatcher,
        CreateMailCapture $mailer,
        PasswordHasher $hasher,
    ): CreateService {
        $mailService = new MailService(
            $mailer,
            $config->mailPath,
            $this->getTranslator(),
            new CreateTestUrlGenerator(),
            $config->appName,
        );

        return new CreateService(
            new UserRepository(),
            $mailService,
            $dispatcher,
            $hasher,
            $config,
        );
    }
}

final class CreateEventCollector implements EventDispatcherInterface
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

final class CreateMailCapture implements MailerInterface
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

final class CreateTestUrlGenerator implements UrlGeneratorInterface
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
