<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Settings;

use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Settings\IndexViewData;

final class IndexViewDataTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testCreateBuildsMenu(): void
    {
        $user = $this->createUser();

        $data = IndexViewData::create(new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), $user);

        self::assertNotEmpty($data->menu->items);
    }

    public function testCreateExposesEmailAndMemberSince(): void
    {
        $user = $this->createUser(email: 'jane@example.com', createdAt: 1700000000);

        $data = IndexViewData::create(new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), $user);

        self::assertSame('jane@example.com', $data->email);
        self::assertNotSame('', $data->memberSinceDisplay);
    }

    public function testCreateUsesProfileNameAsDisplayNameWhenProfileExists(): void
    {
        $user = $this->createUser(username: 'hasprofileuser');
        $this->createUserProfile((int) $user->getId(), 'Jane Doe');

        $data = IndexViewData::create(new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), $user);

        self::assertSame('Jane Doe', $data->displayName);
    }

    public function testCreateUsesUsernameAsDisplayNameWhenNoProfileExists(): void
    {
        $user = $this->createUser(username: 'noprofileuser');

        $data = IndexViewData::create(new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), $user);

        self::assertSame('noprofileuser', $data->displayName);
    }

    private function createUserProfile(int $userId, string $name): void
    {
        $profile = new UserProfile();
        $profile->setUserId($userId);
        $profile->setName($name);
        $profile->save();
    }
}
