<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use YiiRocks\Voyti\Entity\User;
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
            passwordHash VARCHAR(60) NOT NULL,
            authKey VARCHAR(32) NOT NULL,
            unconfirmedEmail VARCHAR(255),
            registrationIp VARCHAR(45),
            flags INTEGER NOT NULL DEFAULT 0,
            confirmedAt INTEGER,
            blockedAt INTEGER,
            updatedAt INTEGER NOT NULL,
            createdAt INTEGER NOT NULL,
            lastLoginAt INTEGER,
            authTfKey VARCHAR(32),
            authTfEnabled INTEGER DEFAULT 0,
            passwordChangedAt INTEGER,
            lastLoginIp VARCHAR(45),
            gdprDeleted INTEGER DEFAULT 0,
            gdprConsent INTEGER DEFAULT 0,
            gdprConsentDate INTEGER,
            authTfType VARCHAR(20),
            authTfMobilePhone VARCHAR(20)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $db = $this->getDb();
        $db->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
        ConnectionProvider::clear();
        parent::tearDown();
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

    public function testPasswordAge(): void
    {
        $user = new User();
        $this->assertEquals(9999, $user->getPasswordAge());
        $user->setPasswordChangedAt(time() - 86400 * 5);
        $this->assertEquals(5, $user->getPasswordAge());
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

    public function testTwoFactorTypeAndMobile(): void
    {
        $user = new User();
        $this->assertNull($user->getAuthTfType());
        $this->assertNull($user->getAuthTfMobilePhone());
        $user->setAuthTfType('sms');
        $user->setAuthTfMobilePhone('+1234567890');
        $this->assertEquals('sms', $user->getAuthTfType());
        $this->assertEquals('+1234567890', $user->getAuthTfMobilePhone());
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
}
