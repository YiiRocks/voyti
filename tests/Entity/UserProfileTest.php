<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserProfileTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user_profile}} (
            user_id INTEGER NOT NULL,
            name VARCHAR(255),
            public_email VARCHAR(255),
            gravatar_email VARCHAR(255),
            location VARCHAR(255),
            website VARCHAR(255),
            bio TEXT,
            timezone VARCHAR(40),
            PRIMARY KEY (user_id)
        )')->execute();
        $db->createCommand('CREATE TABLE {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(60) NOT NULL,
            auth_key VARCHAR(32) NOT NULL,
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
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
            ConnectionProvider::clear();
        }
        parent::tearDown();
    }

    public function testCreateAndFind(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(1);
        $userProfile->setName('John Doe');
        $userProfile->setPublicEmail('john@example.com');
        $userProfile->setGravatarEmail('john@gravatar.com');
        $expectedGravatarId = hash('sha256', trim('john@gravatar.com'));
        $userProfile->setLocation('New York');
        $userProfile->setWebsite('https://johndoe.com');
        $userProfile->setBio('A cool developer');
        $userProfile->setTimezone('America/New_York');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 1])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertTrue((new \ReflectionMethod(UserProfile::class, 'getGravatarId'))->isPublic());
        $this->assertSame(1, $found->getUserId());
        $this->assertSame('John Doe', $found->getName());
        $this->assertSame('john@example.com', $found->getPublicEmail());
        $this->assertSame('john@gravatar.com', $found->getGravatarEmail());
        $this->assertSame($expectedGravatarId, $found->getGravatarId());
        $this->assertSame('New York', $found->getLocation());
        $this->assertSame('https://johndoe.com', $found->getWebsite());
        $this->assertSame('A cool developer', $found->getBio());
        $this->assertSame('America/New_York', $found->getTimezone());
    }

    public function testDeleteProfile(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(3);
        $userProfile->setName('To Delete');
        $userProfile->save();

        $userProfile->delete();

        $found = UserProfile::query()->where(['user_id' => 3])->one();
        $this->assertNull($found);
    }

    public function testEmptyGravatarEmailFallsBackToPublicEmail(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(80);
        $userProfile->setGravatarEmail('');
        $userProfile->setPublicEmail(' Public@Example.com ');

        $this->assertSame(
            hash('sha256', 'public@example.com'),
            $userProfile->getGravatarId(),
        );
    }

    public function testGetUserReturnsMatchingRelatedUser(): void
    {
        $user = $this->createUser('first-related', 'first@example.com', 'hash2', 'auth2');
        $otherUser = $this->createUser('second-related', 'second@example.com', 'hash3', 'auth3');

        $userProfile = new UserProfile();
        $userProfile->setUserId((int) $user->getId());
        $userProfile->save();

        $otherProfile = new UserProfile();
        $otherProfile->setUserId((int) $otherUser->getId());
        $otherProfile->save();

        $found = UserProfile::query()->where(['user_id' => (int) $user->getId()])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $relatedUser = $found->getUser();
        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertSame($user->getId(), $relatedUser->getId());
        $this->assertSame('first-related', $relatedUser->getUsername());
    }

    public function testGetUserReturnsNullWhenRelatedUserIsMissing(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(999);
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 999])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertNull($found->getUser());
        $this->assertNull($found->getGravatarId());
    }

    public function testGravatarEmailTakesPrecedenceOverPublicEmail(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(8);
        $userProfile->setPublicEmail('public@example.com');
        $userProfile->setGravatarEmail('avatar@example.com');

        $this->assertSame(
            hash('sha256', 'avatar@example.com'),
            $userProfile->getGravatarId(),
        );
    }

    public function testGravatarIdAutoPopulatedFromEmail(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(5);
        $userProfile->setName('Gravatar Test');
        $userProfile->setGravatarEmail('test@gravatar.com');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 5])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertSame(hash('sha256', trim('test@gravatar.com')), $found->getGravatarId());
    }

    public function testGravatarIdClearedWhenEmailRemoved(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(6);
        $userProfile->setGravatarEmail('test2@gravatar.com');
        $userProfile->save();

        $userProfile->setGravatarEmail('');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 6])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertNull($found->getGravatarId());
    }

    public function testGravatarIdFallsBackToUserEmail(): void
    {
        $user = $this->createUser('fallback-user', '  User@Example.com ', 'hash1', 'auth1');

        $userProfile = new UserProfile();
        $userProfile->setUserId((int) $user->getId());
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => (int) $user->getId()])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertSame(
            hash('sha256', 'user@example.com'),
            $found->getGravatarId(),
        );
    }

    public function testGravatarIdIgnoresStaleSchemaCacheWhenUserTableIsDropped(): void
    {
        $db = $this->getDb();

        // Prime the schema's internal cache with the "table exists" state, so a
        // non-refreshing lookup would keep returning a (now stale) non-null schema.
        $this->assertNotNull($db->getSchema()->getTableSchema('{{%user}}'));

        $db->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();

        $userProfile = new UserProfile();
        $userProfile->setUserId(321);

        // getGravatarId() must force a schema refresh to notice the table is gone and
        // bail out early; otherwise it would trust the stale cached schema and go on to
        // query the now-missing table via getUser(), causing a database error instead.
        $this->assertNull($userProfile->getGravatarId());
    }

    public function testGravatarIdReturnsNullWhenUserTableIsMissing(): void
    {
        $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();

        $userProfile = new UserProfile();
        $userProfile->setUserId(123);

        $this->assertNull($userProfile->getGravatarId());
    }

    public function testGravatarIdUsesNormalizedPublicEmail(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(7);
        $userProfile->setPublicEmail('  Public@Example.com  ');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 7])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertSame(
            hash('sha256', 'public@example.com'),
            $found->getGravatarId(),
        );
    }


    public function testNullDefaults(): void
    {
        $userProfile = new UserProfile();
        $this->assertNull($userProfile->getName());
        $this->assertNull($userProfile->getPublicEmail());
        $this->assertNull($userProfile->getGravatarEmail());
        $this->assertNull($userProfile->getGravatarId());
        $this->assertNull($userProfile->getLocation());
        $this->assertNull($userProfile->getWebsite());
        $this->assertNull($userProfile->getBio());
        $this->assertNull($userProfile->getTimezone());
    }

    public function testPartialFields(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(4);
        $userProfile->setName('Partial');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 4])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertSame('Partial', $found->getName());
        $this->assertNull($found->getWebsite());
        $this->assertNull($found->getBio());
    }

    public function testUpdateProfile(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(2);
        $userProfile->setName('Jane Doe');
        $userProfile->save();

        $userProfile->setName('Jane Smith');
        $userProfile->setLocation('Los Angeles');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 2])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertSame('Jane Smith', $found->getName());
        $this->assertSame('Los Angeles', $found->getLocation());
    }

    private function createUser(string $username, string $email, string $passwordHash, string $authKey): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($passwordHash);
        $user->setAuthKey($authKey);
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
