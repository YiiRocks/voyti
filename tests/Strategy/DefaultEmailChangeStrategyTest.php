<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Factory\MailFactory;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Strategy\DefaultEmailChangeStrategy;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Router\UrlGeneratorInterface;

final class DefaultEmailChangeStrategyTest extends TestCase
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

    public function testRunPersistsUnconfirmedEmailAndReturnsTrueWhenMailSent(): void
    {
        $user = $this->createPersistedUser('alice', 'alice@example.com');

        $mailer = new MailCapture();
        $strategy = $this->createStrategy($user, 'alice-new@example.com', $mailer);

        $result = $strategy->run();

        self::assertTrue($result);
        self::assertCount(1, $mailer->messages());
        self::assertSame('alice@example.com', $mailer->messages()[0]->getTo());

        $reloaded = User::query()->findByPk($user->getId());
        self::assertSame(
            'alice-new@example.com',
            $reloaded->getUnconfirmedEmail(),
            'user->save() must persist the unconfirmed email to the database',
        );

        $tokens = UserToken::query()->all();
        self::assertCount(1, $tokens);
        self::assertSame((int) $user->getId(), $tokens[0]->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRM_NEW_EMAIL, $tokens[0]->getType());
    }

    public function testRunReturnsFalseWhenUserIsNull(): void
    {
        $form = new SettingsForm($this->getTranslator());
        $form->email = 'new@example.com';

        $tokenFactory = new UserTokenFactory(new UserTokenRepository());
        $mailer = new MailCapture();
        $mailFactory = new MailFactory(new MailService($mailer, dirname(__DIR__, 2) . '/src/resources/mail', $this->getTranslator(), new TestUrlGenerator()));

        $strategy = new DefaultEmailChangeStrategy($form, $tokenFactory, $mailFactory);

        self::assertFalse($strategy->run());
        self::assertCount(0, $mailer->messages());
        self::assertCount(0, UserToken::query()->all());
    }

    public function testRunUsesZeroFallbackTokenUserIdWhenUserIdIsNull(): void
    {
        $user = new User();
        $user->setUsername('bob');
        $user->setEmail('bob@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        self::assertNull($user->getId());

        $mailer = new MailCapture();
        $strategy = $this->createStrategy($user, 'bob-new@example.com', $mailer);

        $result = $strategy->run();

        self::assertTrue($result);

        $tokens = UserToken::query()->all();
        self::assertCount(1, $tokens);
        self::assertSame(
            0,
            $tokens[0]->getUserId(),
            'when the user has no id yet, the token must fall back to user id 0, not -1 or 1',
        );
    }

    private function createPersistedUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }

    private function createStrategy(User $user, string $newEmail, MailCapture $mailer): DefaultEmailChangeStrategy
    {
        $form = new SettingsForm($this->getTranslator());
        $form->email = $newEmail;
        $form->setUser($user);

        $tokenFactory = new UserTokenFactory(new UserTokenRepository());
        $mailService = new MailService($mailer, dirname(__DIR__, 2) . '/src/resources/mail', $this->getTranslator(), new TestUrlGenerator());
        $mailFactory = new MailFactory($mailService);

        return new DefaultEmailChangeStrategy($form, $tokenFactory, $mailFactory);
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
