<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac\Rule;

use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\UpdateViewData;

final class UpdateViewDataTest extends TestCase
{
    public function testCreateBuildsUpdateUrlFromPreviousName(): void
    {
        $model = new RuleForm($this->createTranslator());
        $model->previousName = 'App\\Rule\\OldRule';

        $translator = $this->createTranslator();

        $data = UpdateViewData::create($model, [], new FakeUrlGenerator(), $translator);

        self::assertStringContainsString('App', $data->formSubmitUrl);
        self::assertSame([], $data->errors);
        self::assertNotEmpty($data->menu->items);
    }
}
