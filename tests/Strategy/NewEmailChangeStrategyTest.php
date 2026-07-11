<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Strategy\NewEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class NewEmailChangeStrategyTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension required.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_token"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    public function testRunPersistsTokenWithRealUserIdWhenUserSaved(): void
    {
        $this->initDb();

        $user = $this->createUser();
        $translator = $this->createTranslator();

        $form = new SettingsForm(new ModuleConfig(), $translator);
        $form->setUser($user);
        $form->email = 'new@example.com';

        $tokenFactory = new UserTokenFactory();

        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService(
            $mailCapture,
            __DIR__ . '/../../resources/mail',
            $translator,
            $urlGenerator,
            'App',
        );

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertTrue($strategy->run());

        $this->assertSame(
            (int) $user->getId(),
            (int) $this->connection->createCommand(
                'SELECT "user_id" FROM "user_token" WHERE "type" = :type',
                ['type' => 2],
            )->queryScalar(),
        );
    }

    public function testRunPersistsTokenWithZeroUserIdWhenUserUnsaved(): void
    {
        $this->initDb();

        $translator = $this->createTranslator();

        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('old@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $this->assertNull($user->getId());

        $form = new SettingsForm(new ModuleConfig(), $translator);
        $form->setUser($user);
        $form->email = 'new@example.com';

        $tokenFactory = new UserTokenFactory();

        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService(
            $mailCapture,
            __DIR__ . '/../../resources/mail',
            $translator,
            $urlGenerator,
            'App',
        );

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertTrue($strategy->run());

        $this->assertSame(
            0,
            (int) $this->connection->createCommand(
                'SELECT "user_id" FROM "user_token" WHERE "type" = :type',
                ['type' => 2],
            )->queryScalar(),
        );
    }

    public function testRunReturnsFalseWhenUserIsNull(): void
    {
        $translator = $this->createTranslator();
        $form = new SettingsForm(new ModuleConfig(), $translator);
        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService($mailCapture, '/tmp', $translator, $urlGenerator, 'App');
        $tokenFactory = new UserTokenFactory();

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertFalse($strategy->run());
    }

    public function testRunReturnsTrueWhenMailSucceeds(): void
    {
        $this->initDb();

        $user = $this->createUser();
        $translator = $this->createTranslator();

        $form = new SettingsForm(new ModuleConfig(), $translator);
        $form->setUser($user);
        $form->email = 'new@example.com';

        $tokenFactory = new UserTokenFactory();

        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService(
            $mailCapture,
            __DIR__ . '/../../resources/mail',
            $translator,
            $urlGenerator,
            'App',
        );

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertTrue($strategy->run());
        $this->assertSame('new@example.com', $user->getUnconfirmedEmail());
        $this->assertCount(1, $mailCapture->getSentMessages());

        $this->assertSame(
            'new@example.com',
            $this->connection->createCommand(
                'SELECT "unconfirmed_email" FROM "user" WHERE "id" = :id',
                ['id' => $user->getId()],
            )->queryScalar(),
        );
    }

    private function createSqliteConnection(): ConnectionInterface
    {
        $dsn = new Dsn('sqlite', ':memory:');
        $driver = new Driver($dsn);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('set')->willReturn(true);
        $cache->method('get')->willReturn(null);
        $schemaCache = new SchemaCache($cache);
        $schemaCache->setEnabled(false);
        return new SqliteConnection($driver, $schemaCache);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $id) => $id);
        return $translator;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('old@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }

    private function initDb(): void
    {
        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE "user" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "username" VARCHAR(255) NOT NULL,
                "email" VARCHAR(255) NOT NULL,
                "password_hash" VARCHAR(255) NOT NULL,
                "auth_key" VARCHAR(32) NOT NULL,
                "auth_tf_enabled" INTEGER NOT NULL DEFAULT 0,
                "auth_tf_key" VARCHAR(64),
                "auth_tf_type" VARCHAR(20),
                "blocked_at" INTEGER,
                "confirmed_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                "flags" INTEGER NOT NULL DEFAULT 0,
                "gdpr_consent" INTEGER NOT NULL DEFAULT 0,
                "gdpr_consent_date" INTEGER,
                "anonymized" INTEGER NOT NULL DEFAULT 0,
                "last_login_at" INTEGER,
                "last_login_ip" VARCHAR(45),
                "password_changed_at" INTEGER,
                "registration_ip" VARCHAR(45),
                "unconfirmed_email" VARCHAR(255),
                "updated_at" INTEGER NOT NULL
            )
        ')->execute();

        $this->connection->createCommand('
            CREATE TABLE "user_token" (
                "user_id" INTEGER NOT NULL,
                "code" VARCHAR(64) NOT NULL,
                "type" SMALLINT NOT NULL,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();
    }
}
