<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor {

    // Intercepts calls to the global random_int() made from within this namespace
    // (PHP resolves unqualified function calls against the current namespace first),
    // so tests can assert on the exact bounds EmailCodeGeneratorService::run() passes.
    function random_int(int $min, int $max): int
    {
        \YiiRocks\Voyti\tests\Service\TwoFactor\EmailCodeGeneratorServiceTest::$capturedMin = $min;
        \YiiRocks\Voyti\tests\Service\TwoFactor\EmailCodeGeneratorServiceTest::$capturedMax = $max;

        return \random_int($min, $max);
    }
}

namespace YiiRocks\Voyti\tests\Service\TwoFactor {

    use YiiRocks\Voyti\Entity\User;
    use YiiRocks\Voyti\ModuleConfig;
    use YiiRocks\Voyti\Service\MailService;
    use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
    use YiiRocks\Voyti\tests\TestCase;
    use Yiisoft\Db\Connection\ConnectionProvider;
    use Yiisoft\Mailer\MailerInterface;
    use Yiisoft\Mailer\MessageInterface;
    use Yiisoft\Mailer\SendResults;
    use Yiisoft\Router\UrlGeneratorInterface;

    final class EmailCodeGeneratorServiceTest extends TestCase
    {
        public static ?int $capturedMax = null;
        public static ?int $capturedMin = null;

        private FakeMailer $mailer;

        #[\Override]
        protected function setUp(): void
        {
            parent::setUp();
            self::$capturedMax = null;
            self::$capturedMin = null;

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

            $this->mailer = new FakeMailer();
        }

        public function testRunGeneratesSixDigitNumericCode(): void
        {
            $service = $this->createService();
            $user = $this->createUser();

            $code = $service->run($user);

            self::assertMatchesRegularExpression('/^\d{6}$/', $code);
        }

        public function testRunReturnsDifferentCodesAcrossCalls(): void
        {
            $service = $this->createService();
            $user = $this->createUser();

            $code1 = $service->run($user);
            $code2 = $service->run($user);

            self::assertNotSame($code1, $code2);
        }

        public function testRunStoresCodeOnUserAndSendsMail(): void
        {
            $service = $this->createService();
            $user = $this->createUser();

            $code = $service->run($user);

            self::assertSame($code, $user->getAuthTfKey());
            self::assertCount(1, $this->mailer->messages());
        }

        public function testRunUsesExactRandomIntBounds(): void
        {
            $service = $this->createService();
            $user = $this->createUser();

            $service->run($user);

            self::assertSame(100000, self::$capturedMin);
            self::assertSame(999999, self::$capturedMax);
        }

        private function createService(): EmailCodeGeneratorService
        {
            $mailService = new MailService(
                $this->mailer,
                ModuleConfig::fromArray([])->mailPath,
                $this->createTranslator(),
                new FakeUrlGenerator(),
            );

            return new EmailCodeGeneratorService($mailService);
        }

        private function createUser(): User
        {
            $user = new User();
            $user->setUsername('twofactoruser');
            $user->setEmail('twofactor@example.com');
            $user->setPasswordHash('some-hash');
            $user->setAuthKey('auth-key');
            $user->setCreatedAt(time());
            $user->setUpdatedAt(time());
            $user->save();

            return $user;
        }
    }

    final class FakeMailer implements MailerInterface
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

    final class FakeUrlGenerator implements UrlGeneratorInterface
    {
        #[\Override]
        public function generate(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null): string
        {
            return '/' . $name;
        }

        #[\Override]
        public function generateAbsolute(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null, ?string $scheme = null, ?string $host = null): string
        {
            return 'https://example.test/' . $name;
        }

        #[\Override]
        public function generateFromCurrent(array $replacedArguments = [], array $queryParameters = [], ?string $hash = null, ?string $fallbackRouteName = null): string
        {
            return '/' . ($fallbackRouteName ?? 'current');
        }

        #[\Override]
        public function getUriPrefix(): string
        {
            return '';
        }

        #[\Override]
        public function setDefaultArgument(string $name, \Stringable|string|int|float|bool|null $value): void
        {
        }

        #[\Override]
        public function setUriPrefix(string $name): void
        {
        }
    }
}
