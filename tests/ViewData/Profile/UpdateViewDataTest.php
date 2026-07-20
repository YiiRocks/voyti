<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Profile;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Profile\UpdateViewData;
use Yiisoft\Translator\TranslatorInterface;

final class UpdateViewDataTest extends TestCase
{
    public function testCreateWithoutSwitchedIdentity(): void
    {
        $user = $this->createUser();

        $data = UpdateViewData::create(
            $user,
            new UserProfile(),
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $this->translator(),
            isSwitched: false,
            originalUser: null,
        );

        self::assertNull($data->switchedBannerMessage);
        self::assertSame('//voyti/profile-update', $data->updateUrl);
        self::assertNotEmpty($data->timezoneOptions);
        self::assertSame('list-group list-group-flush', $data->profile->profilePreviewClass);
    }

    public function testCreateWithSwitchedIdentityIncludesOriginalUsername(): void
    {
        $originalUser = $this->createUser(username: 'admin');

        $data = UpdateViewData::create(
            $this->createUser(username: 'switcheduser'),
            new UserProfile(),
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $this->translator(),
            isSwitched: true,
            originalUser: $originalUser,
        );

        self::assertNotNull($data->switchedBannerMessage);
        self::assertStringContainsString('admin', $data->switchedBannerMessage);
    }

    private function createUser(string $username = 'testuser'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        return $user;
    }

    private function translator(): TranslatorInterface
    {
        return $this->createTranslator();
    }
}
