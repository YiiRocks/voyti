<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Admin\User\AssignmentsViewData;
use Yiisoft\Translator\Translator;

final class AssignmentsViewDataTest extends TestCase
{
    public function testCreateSplitsAssignedAndAvailableNames(): void
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

        $data = AssignmentsViewData::create(
            $user,
            ['admin'],
            ['editor' => null, 'viewer' => null],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame(['admin'], $data->assignedItemNames);
        self::assertSame(['editor', 'viewer'], $data->availableItemNames);
        self::assertSame('//voyti/admin-users-assignments?id=999999', $data->formSubmitUrl);
    }
}
