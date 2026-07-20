<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac;

use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\Rbac\IndexViewData;
use Yiisoft\Rbac\Role;

final class IndexViewDataTest extends TestCase
{
    public function testCreateBuildsRowsWithChildrenAndUrls(): void
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
}
