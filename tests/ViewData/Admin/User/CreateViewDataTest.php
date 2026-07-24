<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\User\CreateViewData;

final class CreateViewDataTest extends TestCase
{
    public function testCreateBuildsItemsAndCarriesFormValues(): void
    {
        $config = ModuleConfigFactory::create();
        $model = new RegistrationForm($config, $this->createTranslator());
        $model->username = 'jane';
        $model->email = 'jane@example.com';

        $translator = $this->createTranslator();

        $data = CreateViewData::create(
            $model,
            ['admin' => null, 'editor' => null],
            ['editor'],
            ['username' => ['taken']],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame('jane', $data->usernameValue);
        self::assertSame('jane@example.com', $data->emailValue);
        self::assertSame('//voyti/admin-users-create', $data->formSubmitUrl);
        self::assertCount(2, $data->items);
        self::assertTrue($data->items[1]->checked);
        self::assertSame(['username' => ['taken']], $data->errors);
        self::assertNotEmpty($data->menu->items);
    }
}
