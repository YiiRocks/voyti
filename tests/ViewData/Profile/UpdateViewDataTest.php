<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Profile;

use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Profile\UpdateViewData;

final class UpdateViewDataTest extends TestCase
{
    use UserFactoryTrait;

    public function testCreate(): void
    {
        $user = $this->buildUser();

        $data = UpdateViewData::create(
            $user,
            new UserProfile(),
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertSame('//voyti/user-profile', $data->updateUrl);
        self::assertNotEmpty($data->timezoneOptions);
        self::assertSame('list-group list-group-flush', $data->profile->profilePreviewClass);
    }
}
