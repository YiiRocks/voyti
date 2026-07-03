<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserTest extends TestCase
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
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }
        parent::tearDown();
    }

    public function testConfirmUserLoadedFromDb(): void
    {
        $user = new User();
        $user->setUsername('confirmuser');
        $user->setEmail('confirm@example.com');
        $user->setPasswordHash('hash1');
        $user->setAuthKey('auth1');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $loaded = User::query()->findByPk($user->getId());
        $this->assertInstanceOf(User::class, $loaded);
        $this->assertFalse($loaded->isConfirmed());

        $loaded->setConfirmedAt(time());
        $loaded->save();

        $found = User::query()->where(['username' => 'confirmuser'])->one();
        $this->assertInstanceOf(User::class, $found);
        $this->assertNotNull($found->getConfirmedAt());
    }

    public function testCreateAndFind(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hashed123');
        $user->setAuthKey('key123');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $found = User::query()->where(['username' => 'testuser'])->one();
        $this->assertInstanceOf(User::class, $found);
        $this->assertSame('testuser', $found->getUsername());
        $this->assertSame('test@example.com', $found->getEmail());
    }

    public function testDeleteUser(): void
    {
        $user = new User();
        $user->setUsername('deleteuser');
        $user->setEmail('delete@example.com');
        $user->setPasswordHash('hash2');
        $user->setAuthKey('auth2');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $user->delete();

        $found = User::query()->where(['username' => 'deleteuser'])->one();
        $this->assertNull($found);
    }

    public function testFlags(): void
    {
        $user = new User();
        $this->assertEquals(0, $user->getFlags());
        $user->setFlags(User::OLD_EMAIL_CONFIRMED | User::NEW_EMAIL_CONFIRMED);
        $this->assertEquals(3, $user->getFlags());
    }

    public function testGdprFlags(): void
    {
        $user = new User();
        $this->assertFalse($user->isGdprDeleted());
        $this->assertFalse($user->isGdprConsent());
        $user->setGdprDeleted(true);
        $user->setGdprConsent(true);
        $user->setGdprConsentDate(12345);
        $this->assertTrue($user->isGdprDeleted());
        $this->assertTrue($user->isGdprConsent());
        $this->assertEquals(12345, $user->getGdprConsentDate());
    }

    public function testGetIdReturnsStringWhenInternalIdSet(): void
    {
        $user = new User();

        $this->assertNull($user->getId());

        $this->setPrivateProperty($user, 'id', 42);

        $this->assertSame('42', $user->getId());
    }

    public function testIsBlocked(): void
    {
        $user = new User();
        $this->assertFalse($user->isBlocked());
        $user->setBlockedAt(time());
        $this->assertTrue($user->isBlocked());
    }

    public function testIsConfirmed(): void
    {
        $user = new User();
        $this->assertFalse($user->isConfirmed());
        $user->setConfirmedAt(time());
        $this->assertTrue($user->isConfirmed());
    }

    public function testLastLoginIp(): void
    {
        $user = new User();
        $this->assertNull($user->getLastLoginIp());
        $user->setLastLoginIp('10.0.0.1');
        $this->assertEquals('10.0.0.1', $user->getLastLoginIp());
    }

    public function testLoadsRelatedProfile(): void
    {
        $user = $this->createUser('profileuser', 'profile@example.com', 'hash3', 'auth3');
        $otherUser = $this->createUser('otherprofileuser', 'otherprofile@example.com', 'hash4', 'auth4');

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName('Profile User');
        $profile->save();

        $otherProfile = new UserProfile();
        $otherProfile->setUserId((int) $otherUser->getId());
        $otherProfile->setName('Other Profile User');
        $otherProfile->save();

        $found = User::query()->where(['username' => 'profileuser'])->one();
        $this->assertInstanceOf(User::class, $found);
        $relatedProfile = $found->getProfile();
        $this->assertInstanceOf(UserProfile::class, $relatedProfile);
        $this->assertSame('Profile User', $relatedProfile->getName());
        $this->assertSame((int) $user->getId(), $relatedProfile->getUserId());
    }

    public function testPasswordAge(): void
    {
        $user = new User();
        $this->assertEquals(9999, $user->getPasswordAge());
        $user->setPasswordChangedAt(time() - 86400 * 5);
        $this->assertEquals(5, $user->getPasswordAge());
    }

    public function testPasswordAgeStaysZeroBeforeFullDayElapsed(): void
    {
        $user = new User();
        $user->setPasswordChangedAt(time() - 86399);

        $this->assertSame(0, $user->getPasswordAge());
    }

    public function testPublicUserApiRemainsPublic(): void
    {
        foreach ([
            'getConfirmedAt',
            'getFlags',
            'getPasswordAge',
            'getRegistrationIp',
            'getUnconfirmedEmail',
            'isConfirmed',
            'isGdprConsent',
            'isGdprDeleted',
            'setAuthTfEnabled',
            'setConfirmedAt',
            'setFlags',
            'setGdprConsent',
            'setGdprConsentDate',
            'setGdprDeleted',
            'setLastLoginAt',
            'setPasswordChangedAt',
            'setRegistrationIp',
            'setUnconfirmedEmail',
            'validateAuthKey',
        ] as $method) {
            $this->assertTrue((new \ReflectionMethod(User::class, $method))->isPublic(), $method . ' should stay public.');
        }
    }

    public function testRegistrationIp(): void
    {
        $user = new User();
        $this->assertNull($user->getRegistrationIp());
        $user->setRegistrationIp('192.168.1.1');
        $this->assertEquals('192.168.1.1', $user->getRegistrationIp());
    }

    public function testTwoFactor(): void
    {
        $user = new User();
        $this->assertNull($user->getAuthTfKey());
        $this->assertFalse($user->isAuthTfEnabled());
        $user->setAuthTfKey('SECRETKEY123');
        $user->setAuthTfEnabled(true);
        $this->assertEquals('SECRETKEY123', $user->getAuthTfKey());
        $this->assertTrue($user->isAuthTfEnabled());
    }

    public function testUnconfirmedEmail(): void
    {
        $user = new User();
        $this->assertNull($user->getUnconfirmedEmail());
        $user->setUnconfirmedEmail('new@example.com');
        $this->assertEquals('new@example.com', $user->getUnconfirmedEmail());
    }

    public function testUpdateUser(): void
    {
        $user = new User();
        $user->setUsername('updateuser');
        $user->setEmail('original@example.com');
        $user->setPasswordHash('hash1');
        $user->setAuthKey('auth1');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $user->setEmail('updated@example.com');
        $user->setUpdatedAt(time());
        $user->save();

        $found = User::query()->where(['username' => 'updateuser'])->one();
        $this->assertInstanceOf(User::class, $found);
        $this->assertSame('updated@example.com', $found->getEmail());
    }

    public function testValidateAuthKey(): void
    {
        $user = new User();
        $user->setAuthKey('secret123');
        $this->assertTrue($user->validateAuthKey('secret123'));
        $this->assertFalse($user->validateAuthKey('wrong'));
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

    private function setPrivateProperty(User $user, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty(User::class, $property);
        $reflectionProperty->setValue($user, $value);
    }
}
