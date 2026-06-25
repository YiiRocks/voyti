<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

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
            gravatar_id VARCHAR(32),
            location VARCHAR(255),
            website VARCHAR(255),
            bio TEXT,
            timezone VARCHAR(40),
            PRIMARY KEY (user_id)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
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
        $expectedGravatarId = md5(trim('john@gravatar.com'));
        $userProfile->setLocation('New York');
        $userProfile->setWebsite('https://johndoe.com');
        $userProfile->setBio('A cool developer');
        $userProfile->setTimezone('America/New_York');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 1])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
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

    public function testGravatarIdAutoPopulatedFromEmail(): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUserId(5);
        $userProfile->setName('Gravatar Test');
        $userProfile->setGravatarEmail('test@gravatar.com');
        $userProfile->save();

        $found = UserProfile::query()->where(['user_id' => 5])->one();
        $this->assertInstanceOf(UserProfile::class, $found);
        $this->assertSame(md5(trim('test@gravatar.com')), $found->getGravatarId());
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
}
