<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac\Rule;

use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\CreateViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\UpdateViewData;
use Yiisoft\Translator\Translator;

final class RuleViewDataTest extends TestCase
{
    public function testCreateViewDataCarriesErrorsAndUrl(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = CreateViewData::create(['class' => ['invalid']], new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/admin-rbac-rules-create', $data->formSubmitUrl);
        self::assertSame(['class' => ['invalid']], $data->errors);
        self::assertNotEmpty($data->menu->items);
    }

    public function testIndexViewDataBuildsRowsWithUrls(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(['App\\Rule\\MyRule'], new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/admin-rbac-rules-create', $data->createUrl);
        self::assertCount(1, $data->rules);
        self::assertSame('App\\Rule\\MyRule', $data->rules[0]->name);
        self::assertSame('//voyti/admin-rbac-rules-update?name=App%5CRule%5CMyRule', $data->rules[0]->updateUrl);
        self::assertSame('//voyti/admin-rbac-rules-delete?name=App%5CRule%5CMyRule', $data->rules[0]->formSubmitUrl);
        self::assertNotEmpty($data->menu->items);
    }

    public function testUpdateViewDataBuildsUpdateUrlFromPreviousName(): void
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
