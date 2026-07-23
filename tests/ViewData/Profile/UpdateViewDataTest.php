<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Profile;

use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Profile\UpdateViewData;

final class UpdateViewDataTest extends TestCase
{
    use UserFactoryTrait;

    public function testCreateWithoutSwitchedIdentity(): void
    {
        $user = $this->buildUser();

        $data = UpdateViewData::create(
            $user,
            new UserProfile(),
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
            isSwitched: false,
            originalUser: null,
        );

        self::assertNull($data->switchedBannerMessage);
        self::assertSame('//voyti/user-profile', $data->updateUrl);
        self::assertNotEmpty($data->timezoneOptions);
        self::assertSame('list-group list-group-flush', $data->profile->profilePreviewClass);
    }

    public function testCreateWithSwitchedIdentityIncludesOriginalUsername(): void
    {
        $originalUser = $this->buildUser(username: 'admin');

        $data = UpdateViewData::create(
            $this->buildUser(username: 'switcheduser'),
            new UserProfile(),
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
            isSwitched: true,
            originalUser: $originalUser,
        );

        self::assertNotNull($data->switchedBannerMessage);
        self::assertStringContainsString('admin', $data->switchedBannerMessage);
    }

}
