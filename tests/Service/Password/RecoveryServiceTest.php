<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Router\UrlGeneratorInterface;

final class RecoveryServiceTest extends TestCase
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
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testRunForBlockedUserDoesNotCreateTokenOrSendMail(): void
    {
        $this->insertUser('carol', 'carol@example.com', blockedAt: time());

        $mailer = new MailCapture();
        $service = $this->createService($mailer);

        $result = $service->run('carol@example.com');

        self::assertTrue($result->isSuccess());
        self::assertSame('If the email exists, a recovery message has been sent', $result->getMessage());
        self::assertCount(0, UserToken::query()->all());
        self::assertCount(0, $mailer->messages());
    }

    public function testRunForKnownUserCreatesTokenAndSendsRecoveryMail(): void
    {
        $this->insertUser('alice', 'alice@example.com');

        $mailer = new MailCapture();
        $service = $this->createService($mailer);

        $before = time();
        $result = $service->run('alice@example.com');
        $after = time();

        self::assertTrue($result->isSuccess());
        self::assertSame('Recovery message sent', $result->getMessage());

        $tokens = UserToken::query()->all();
        self::assertCount(1, $tokens);
        self::assertInstanceOf(UserToken::class, $tokens[0]);

        // Kills DecrementInteger/IncrementInteger on the ternary fallback: the
        // real user id (1) must be used, not the -1/0/1 fallback branch.
        self::assertSame(1, $tokens[0]->getUserId());
        self::assertSame(UserToken::TYPE_RECOVERY, $tokens[0]->getType());
        self::assertGreaterThanOrEqual($before, $tokens[0]->getCreatedAt());
        self::assertLessThanOrEqual($after, $tokens[0]->getCreatedAt());

        // Kills DecrementInteger/IncrementInteger/MethodCallRemoval on setCode(Random::string(32)):
        // the code must be a non-empty 32-character string, not 31/33 chars or the empty default.
        self::assertSame(32, strlen($tokens[0]->getCode()));
        self::assertNotSame('', $tokens[0]->getCode());

        // Kills MethodCallRemoval on the sendRecovery(...) call: the mailer must
        // actually receive a message built from the saved token/user data.
        $messages = $mailer->messages();
        self::assertCount(1, $messages);
        self::assertSame('alice@example.com', $messages[0]->getTo());
        self::assertStringContainsString('alice', (string) $messages[0]->getHtmlBody());
        self::assertStringContainsString(
            'https://example.test/voyti/recover?id=1&code=' . $tokens[0]->getCode(),
            (string) $messages[0]->getTextBody(),
        );
    }

    public function testRunForUnknownEmailDoesNotCreateTokenOrSendMail(): void
    {
        $mailer = new MailCapture();
        $service = $this->createService($mailer);

        $result = $service->run('nobody@example.com');

        self::assertTrue($result->isSuccess());
        self::assertSame('If the email exists, a recovery message has been sent', $result->getMessage());
        self::assertCount(0, UserToken::query()->all());
        self::assertCount(0, $mailer->messages());
    }

    private function createService(MailCapture $mailer): RecoveryService
    {
        $config = ModuleConfig::fromArray([]);
        $mailService = new MailService(
            $mailer,
            $config->mailPath,
            $this->getTranslator(),
            new TestUrlGenerator(),
            $config->appName,
        );

        return new RecoveryService(
            new UserRepository(),
            new UserTokenFactory(new UserTokenRepository()),
            $mailService,
            $config,
            $this->getTranslator(),
            new RecoveryServiceEventCollector(),
        );
    }

    private function insertUser(string $username, string $email, ?int $blockedAt = null): void
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setBlockedAt($blockedAt);
        $user->save();
    }
}

final class RecoveryServiceEventCollector implements EventDispatcherInterface
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
