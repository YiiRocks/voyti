<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Admin\User\InfoViewData;
use Yiisoft\Translator\Translator;

final class InfoViewDataTest extends TestCase
{
    public function testCreateShowsAdminFields(): void
    {
        $createdAt = time();
        $user = new User();
        $user->setUsername('jane');
        $user->setEmail('jane@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt($createdAt);
        $user->setUpdatedAt($createdAt);

        $profile = new UserProfile();
        $profile->setTimezone('America/New_York');

        $translator = new Translator('en', null, 'voyti');

        $data = InfoViewData::create($user, $profile, new FakeUrlGenerator(), $translator, 'Asia/Tokyo');

        self::assertSame('jane', $data->username);
        self::assertTrue($data->profile->showAdminFields);
        self::assertSame('list-group list-group-flush', $data->profile->profilePreviewClass);
        self::assertNotEmpty($data->menu->items);
        self::assertSame(
            TimezoneHelper::formatLocalized($createdAt, $translator->getLocale(), 'Asia/Tokyo'),
            $data->profile->registeredDisplay,
        );
    }
}
