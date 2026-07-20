<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Admin\User\ProfileViewData;
use Yiisoft\Translator\Translator;

final class ProfileViewDataTest extends TestCase
{
    public function testCreateBuildsUpdateUrlAndTimezoneOptions(): void
    {
        $user = new User();
        $user->setUsername('jane');
        $user->setEmail('jane@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 999999);

        $translator = new Translator('en', null, 'voyti');

        $data = ProfileViewData::create($user, new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/admin-users-update-profile?id=999999', $data->formSubmitUrl);
        self::assertNotEmpty($data->timezoneOptions);
    }
}
