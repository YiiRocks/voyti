<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Strategy\MailChangeStrategyInterface;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class EmailChangeServiceTest extends TestCase
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

    public function testRunDeletesTokenAfterSuccessfulProcessing(): void
    {
        $user = $this->createUser('dave@example.com', 'dave-new@example.com');
        $this->createToken((int) $user->getId(), 'good-code', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_DEFAULT,
        ]));
        $result = $service->run('good-code', $user);

        self::assertTrue($result);
        self::assertNull(
            (new UserTokenRepository())->findByUserIdAndCode((int) $user->getId(), 'good-code'),
        );
    }

    public function testRunFinalConditionRequiresBothFlagsBitwiseAndNotJustNewEmailConfirmed(): void
    {
        // Kills the BitwiseAnd mutant on line 63, which changes
        // "$user->getFlags() & User::OLD_EMAIL_CONFIRMED" (a genuine bit test) into
        // "$user->getFlags() | User::OLD_EMAIL_CONFIRMED" (always truthy, since
        // OLD_EMAIL_CONFIRMED is a non-zero constant). To isolate this term we must reach
        // the final condition with NEW_EMAIL_CONFIRMED already set (so the first &&
        // operand is true) but OLD_EMAIL_CONFIRMED NOT set, without going through the
        // SECURE-strategy branch that would touch the flags itself and without the
        // DEFAULT strategy short-circuiting the whole condition to true. TYPE_INSECURE
        // satisfies both: it skips the SECURE flag-setting block entirely and is not
        // TYPE_DEFAULT, so the pre-set flags reach the final check untouched.
        $user = $this->createUser('mona@example.com', 'mona-new@example.com');
        $user->setFlags(User::NEW_EMAIL_CONFIRMED);
        $user->save();
        $this->createToken((int) $user->getId(), 'insecure-code', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_INSECURE,
        ]));

        $result = $service->run('insecure-code', $user);

        self::assertTrue($result);
        self::assertSame('mona@example.com', $user->getEmail());
        self::assertSame('mona-new@example.com', $user->getUnconfirmedEmail());
        self::assertSame(User::NEW_EMAIL_CONFIRMED, $user->getFlags());
    }

    public function testRunFinalizationUpdatesTimestampToCurrentTime(): void
    {
        // Kills the MethodCallRemoval mutant on $user->setUpdatedAt(time()) (line 68).
        // The user's updated_at is deliberately backdated far into the past before
        // calling run(), so that a genuine update to "now" is unambiguously observable
        // and cannot be confused with a value that already happened to satisfy a loose
        // ">=" comparison against a freshly-captured "before" timestamp.
        $user = $this->createUser('nina@example.com', 'nina-new@example.com');
        $staleTimestamp = time() - 10000;
        $user->setUpdatedAt($staleTimestamp);
        $user->save();
        $this->createToken((int) $user->getId(), 'stale-code', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_DEFAULT,
        ]));

        $before = time();
        $result = $service->run('stale-code', $user);
        $after = time();

        self::assertTrue($result);
        self::assertGreaterThanOrEqual($before, $user->getUpdatedAt());
        self::assertLessThanOrEqual($after, $user->getUpdatedAt());

        $reloaded = User::query()->findByPk($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertNotSame($staleTimestamp, $reloaded->getUpdatedAt());
    }

    public function testRunReturnsFalseWhenNoUserTokenMatchesUserIdAndCode(): void
    {
        $user = $this->createUser('alice@example.com', 'alice-new@example.com');

        $service = $this->createService();

        // No token was ever created, so findByUserIdAndCode() returns null.
        $result = $service->run('does-not-exist', $user);

        self::assertFalse($result);
    }

    public function testRunReturnsFalseWhenTokenTypeIsNotAnEmailChangeType(): void
    {
        $user = $this->createUser('carol@example.com', 'carol-new@example.com');
        $this->createToken((int) $user->getId(), 'confirm-code', UserToken::TYPE_CONFIRMATION);

        $service = $this->createService();
        $result = $service->run('confirm-code', $user);

        self::assertFalse($result);

        // Token of the wrong type must not have been deleted by this call.
        self::assertNotNull(
            (new UserTokenRepository())->findByUserIdAndCode((int) $user->getId(), 'confirm-code'),
        );
    }

    public function testRunReturnsFalseWhenUnconfirmedEmailAlreadyBelongsToAnotherUser(): void
    {
        $this->createUser('taken@example.com', null);
        $user = $this->createUser('frank@example.com', 'taken@example.com');
        $this->createToken((int) $user->getId(), 'clash-code', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService();
        $result = $service->run('clash-code', $user);

        self::assertFalse($result);
        // The user's own email must remain unchanged.
        self::assertSame('frank@example.com', $user->getEmail());
    }

    public function testRunReturnsFalseWhenUnconfirmedEmailIsNull(): void
    {
        $user = $this->createUser('erin@example.com', null);
        $this->createToken((int) $user->getId(), 'no-unconfirmed', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService();
        $result = $service->run('no-unconfirmed', $user);

        self::assertFalse($result);
    }

    public function testRunUsesZeroFallbackUserIdWhenUserIdIsNull(): void
    {
        // Guards against the DecrementInteger/IncrementInteger mutants that change the
        // fallback user id used when $user->getId() is null (0 -> -1 / 0 -> 1). A token
        // stored under user_id=0 must be found for a never-persisted (id-less) $user;
        // with the mutated fallback (-1 or 1) the lookup would fail to find it and the
        // service would short-circuit to false instead of applying the email change.
        $this->createToken(0, 'zero-id-code', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $user = new User();
        $user->setUsername('bob');
        $user->setEmail('bob@example.com');
        $user->setUnconfirmedEmail('bob-new@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setUpdatedAt(time());
        self::assertNull($user->getId());

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_DEFAULT,
        ]));
        $result = $service->run('zero-id-code', $user);

        self::assertTrue($result);
        self::assertSame('bob-new@example.com', $user->getEmail());
    }

    public function testRunWithDefaultStrategyAppliesEmailChangeAndResetsFlagsAndUpdatesTimestamp(): void
    {
        $user = $this->createUser('greg@example.com', 'greg-new@example.com');
        $user->setFlags(User::NEW_EMAIL_CONFIRMED);
        $user->save();
        $this->createToken((int) $user->getId(), 'default-code', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_DEFAULT,
        ]));

        $before = time();
        $result = $service->run('default-code', $user);
        $after = time();

        self::assertTrue($result);
        self::assertSame('greg-new@example.com', $user->getEmail());
        self::assertNull($user->getUnconfirmedEmail());
        self::assertSame(0, $user->getFlags());
        self::assertGreaterThanOrEqual($before, $user->getUpdatedAt());
        self::assertLessThanOrEqual($after, $user->getUpdatedAt());

        $reloaded = User::query()->findByPk($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('greg-new@example.com', $reloaded->getEmail());
        self::assertNull($reloaded->getUnconfirmedEmail());
        self::assertSame(0, $reloaded->getFlags());
    }

    public function testRunWithSecureStrategyBothFlagsConfirmedAppliesEmailChangeAndResetsFlags(): void
    {
        // Sets up the OLD_EMAIL_CONFIRMED flag beforehand so that when the token of type
        // TYPE_CONFIRM_NEW_EMAIL... actually let's use TYPE_CONFIRM_OLD_EMAIL as the final
        // token so both flags end up set together and the finalization branch runs.
        $user = $this->createUser('holly@example.com', 'holly-new@example.com');
        $user->setFlags(User::NEW_EMAIL_CONFIRMED);
        $user->save();
        $this->createToken((int) $user->getId(), 'secure-code', UserToken::TYPE_CONFIRM_OLD_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_SECURE,
        ]));

        $result = $service->run('secure-code', $user);

        self::assertTrue($result);
        self::assertSame('holly-new@example.com', $user->getEmail());
        self::assertNull($user->getUnconfirmedEmail());
        self::assertSame(0, $user->getFlags());
    }

    public function testRunWithSecureStrategyNewEmailConfirmedBranchPersistsFlagsToDatabase(): void
    {
        // Kills the MethodCallRemoval mutant on the $user->save() call inside the
        // TYPE_CONFIRM_NEW_EMAIL branch of the SECURE strategy (line 53). That branch
        // returns immediately afterwards, so the bottom-of-method save() at line 71 is
        // never reached; without its own save() call the NEW_EMAIL_CONFIRMED flag would
        // never be persisted. Asserting on the in-memory $user object cannot detect this
        // (setFlags() already mutated it regardless of save()), so we reload a fresh
        // instance from the database instead.
        $user = $this->createUser('kate@example.com', 'kate-new@example.com');
        $this->createToken((int) $user->getId(), 'new-email-code', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_SECURE,
        ]));

        $result = $service->run('new-email-code', $user);

        self::assertTrue($result);

        $reloaded = User::query()->findByPk($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame(User::NEW_EMAIL_CONFIRMED, $reloaded->getFlags());
    }

    public function testRunWithSecureStrategyNewEmailConfirmedBranchReturnsBeforeFinalizingEvenWithBothFlagsSet(): void
    {
        // Kills the ReturnRemoval mutant on the "return true;" at the end of the
        // TYPE_CONFIRM_NEW_EMAIL branch of the SECURE strategy (line 54). To make this
        // observable, the user already carries OLD_EMAIL_CONFIRMED before the run() call,
        // so that once this branch OR's in NEW_EMAIL_CONFIRMED, both confirmation flags
        // are simultaneously true. The original code still returns immediately without
        // reaching the finalization block below, so the email must remain unconfirmed.
        // The mutant (no early return) falls through to the bottom-of-method condition,
        // where "both flags set" becomes true, and would wrongly finalize the email
        // change right here.
        $user = $this->createUser('leo@example.com', 'leo-new@example.com');
        $user->setFlags(User::OLD_EMAIL_CONFIRMED);
        $user->save();
        $this->createToken((int) $user->getId(), 'new-email-code-2', UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_SECURE,
        ]));

        $result = $service->run('new-email-code-2', $user);

        self::assertTrue($result);
        self::assertSame('leo@example.com', $user->getEmail());
        self::assertSame('leo-new@example.com', $user->getUnconfirmedEmail());
        self::assertSame(User::OLD_EMAIL_CONFIRMED | User::NEW_EMAIL_CONFIRMED, $user->getFlags());
    }

    public function testRunWithSecureStrategyOldEmailConfirmedAloneDoesNotApplyEmailChangeYet(): void
    {
        // Reaches the final "apply email change" condition with strategy=SECURE (A=false)
        // and only OLD_EMAIL_CONFIRMED set, i.e. NOT both flags confirmed (B=false). Unlike
        // the TYPE_CONFIRM_NEW_EMAIL branch, TYPE_CONFIRM_OLD_EMAIL does not return early, so
        // this genuinely exercises the (A || B) condition with A=false and B=false: the
        // original leaves the condition false (no email change yet), while the
        // LogicalOrAllSubExprNegation mutant (!A || !B) evaluates to true and would wrongly
        // apply the email change immediately.
        $user = $this->createUser('jack@example.com', 'jack-new@example.com');
        $this->createToken((int) $user->getId(), 'old-email-code', UserToken::TYPE_CONFIRM_OLD_EMAIL);

        $service = $this->createService(ModuleConfig::fromArray([
            'emailChangeStrategy' => MailChangeStrategyInterface::TYPE_SECURE,
        ]));

        $result = $service->run('old-email-code', $user);

        self::assertTrue($result);
        self::assertSame('jack@example.com', $user->getEmail());
        self::assertSame('jack-new@example.com', $user->getUnconfirmedEmail());
        self::assertSame(User::OLD_EMAIL_CONFIRMED, $user->getFlags());
    }

    private function createService(?ModuleConfig $config = null): EmailChangeService
    {
        return new EmailChangeService(
            $config ?? new ModuleConfig(),
            new UserTokenRepository(),
            new UserRepository(),
        );
    }

    private function createToken(int $userId, string $code, int $type): UserToken
    {
        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode($code);
        $token->setType($type);
        $token->setCreatedAt(time());
        $token->save();

        return $token;
    }

    private function createUser(string $email, ?string $unconfirmedEmail): User
    {
        static $counter = 0;
        $counter++;

        $user = new User();
        $user->setUsername('user' . $counter);
        $user->setEmail($email);
        $user->setUnconfirmedEmail($unconfirmedEmail);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
