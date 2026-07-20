<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac;

use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\Rbac\UpdateViewData;

final class UpdateViewDataTest extends TestCase
{
    public function testCreateBuildsTitleChildrenAndAssignedUsers(): void
    {
        $model = new AuthItemForm($this->createTranslator(), 'role');
        $model->itemName = 'editor';
        $model->children = ['admin'];

        $user = new User();
        $user->setUsername('jane');
        $user->setEmail('jane@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        $translator = $this->createTranslator();

        $data = UpdateViewData::create(
            'role',
            $model,
            ['admin' => null, 'editor' => null],
            [$user],
            [],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame('Update role: editor', $data->title);
        self::assertSame('//voyti/admin-rbac-roles-update?name=editor', $data->formSubmitUrl);
        self::assertTrue($data->children[0]->checked);
        self::assertCount(1, $data->assignedUsers);
        self::assertSame('jane', $data->assignedUsers[0]->username);
    }
}
