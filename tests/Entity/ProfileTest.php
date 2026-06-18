<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use Yiisoft\Db\Connection\ConnectionProvider;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\Entity\Profile;

final class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%profile}} (
            userId INTEGER NOT NULL,
            name VARCHAR(255),
            publicEmail VARCHAR(255),
            gravatarEmail VARCHAR(255),
            gravatarId VARCHAR(32),
            location VARCHAR(255),
            website VARCHAR(255),
            bio TEXT,
            timezone VARCHAR(40),
            PRIMARY KEY (userId)
        )')->execute();
    }

    protected function tearDown(): void
    {
        $db = $this->getDb();
        $db->createCommand('DROP TABLE IF EXISTS {{%profile}}')->execute();
        ConnectionProvider::clear();
        parent::tearDown();
    }

    public function testCreateAndFind(): void
    {
        $profile = new Profile();
        $profile->setUserId(1);
        $profile->setName('John Doe');
        $profile->setPublicEmail('john@example.com');
        $profile->setGravatarEmail('john@gravatar.com');
        $expectedGravatarId = md5(strtolower(trim('john@gravatar.com')));
        $profile->setLocation('New York');
        $profile->setWebsite('https://johndoe.com');
        $profile->setBio('A cool developer');
        $profile->setTimezone('America/New_York');
        $profile->save();

        $found = Profile::query()->where(['userId' => 1])->one();
        $this->assertNotNull($found);
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

    public function testNullDefaults(): void
    {
        $profile = new Profile();
        $this->assertNull($profile->getName());
        $this->assertNull($profile->getPublicEmail());
        $this->assertNull($profile->getGravatarEmail());
        $this->assertNull($profile->getGravatarId());
        $this->assertNull($profile->getLocation());
        $this->assertNull($profile->getWebsite());
        $this->assertNull($profile->getBio());
        $this->assertNull($profile->getTimezone());
    }

    public function testUpdateProfile(): void
    {
        $profile = new Profile();
        $profile->setUserId(2);
        $profile->setName('Jane Doe');
        $profile->save();

        $profile->setName('Jane Smith');
        $profile->setLocation('Los Angeles');
        $profile->save();

        $found = Profile::query()->where(['userId' => 2])->one();
        $this->assertNotNull($found);
        $this->assertSame('Jane Smith', $found->getName());
        $this->assertSame('Los Angeles', $found->getLocation());
    }

    public function testDeleteProfile(): void
    {
        $profile = new Profile();
        $profile->setUserId(3);
        $profile->setName('To Delete');
        $profile->save();

        $profile->delete();

        $found = Profile::query()->where(['userId' => 3])->one();
        $this->assertNull($found);
    }

    public function testPartialFields(): void
    {
        $profile = new Profile();
        $profile->setUserId(4);
        $profile->setName('Partial');
        $profile->save();

        $found = Profile::query()->where(['userId' => 4])->one();
        $this->assertSame('Partial', $found->getName());
        $this->assertNull($found->getWebsite());
        $this->assertNull($found->getBio());
    }

    public function testGravatarIdAutoPopulatedFromEmail(): void
    {
        $profile = new Profile();
        $profile->setUserId(5);
        $profile->setName('Gravatar Test');
        $profile->setGravatarEmail('test@gravatar.com');
        $profile->save();

        $found = Profile::query()->where(['userId' => 5])->one();
        $this->assertSame(md5(strtolower(trim('test@gravatar.com'))), $found->getGravatarId());
    }

    public function testGravatarIdClearedWhenEmailRemoved(): void
    {
        $profile = new Profile();
        $profile->setUserId(6);
        $profile->setGravatarEmail('test2@gravatar.com');
        $profile->save();

        $profile->setGravatarEmail('');
        $profile->save();

        $found = Profile::query()->where(['userId' => 6])->one();
        $this->assertNull($found->getGravatarId());
    }
}
