<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac;

use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\Rbac\CreateViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\UpdateViewData;
use Yiisoft\Rbac\Role;

final class RbacViewDataTest extends TestCase
{
    public function testCreateViewDataMarksSelectedChildrenAsChecked(): void
    {
        $model = new AuthItemForm($this->createTranslator(), 'role');
        $model->children = ['editor'];

        $translator = $this->createTranslator();

        $data = CreateViewData::create(
            'role',
            $model,
            ['admin' => null, 'editor' => null],
            ['name' => ['taken']],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame('Create role', $data->title);
        self::assertSame('//voyti/admin-rbac-roles-create', $data->formSubmitUrl);
        self::assertCount(2, $data->children);
        self::assertTrue($data->children[1]->checked);
        self::assertSame(['name' => ['taken']], $data->errors);
    }

    public function testIndexViewDataBuildsRowsWithChildrenAndUrls(): void
    {
        $role = (new Role('editor'))->withDescription('Can edit');
        $translator = $this->createTranslator();

        $data = IndexViewData::create(
            'role',
            ['editor' => $role],
            ['editor' => ['viewer']],
            'ed',
            '',
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame('Roles', $data->title);
        self::assertSame('Create role', $data->createLinkLabel);
        self::assertSame('//voyti/admin-rbac-roles-create', $data->createUrl);
        self::assertSame('//voyti/admin-rbac-roles', $data->filterUrl);
        self::assertSame('ed', $data->filterName);
        self::assertSame('viewer', $data->items[0]->childrenDisplay);
        self::assertSame('//voyti/admin-rbac-roles-update?name=editor', $data->items[0]->updateUrl);
        self::assertSame('//voyti/admin-rbac-roles-delete?name=editor', $data->items[0]->formSubmitUrl);
        self::assertNotEmpty($data->menu->items);
    }

    public function testUpdateViewDataBuildsTitleChildrenAndAssignedUsers(): void
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
