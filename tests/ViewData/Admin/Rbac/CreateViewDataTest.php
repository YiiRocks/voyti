<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac;

use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\Rbac\CreateViewData;

final class CreateViewDataTest extends TestCase
{
    public function testCreateMarksSelectedChildrenAsChecked(): void
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
}
