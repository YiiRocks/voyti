<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\User\AccountViewData;

final class AccountViewDataTest extends TestCase
{
    public function testCreateBuildsTitleAndItems(): void
    {
        $user = new User();
        $user->setUsername('jane');
        $user->setEmail('jane@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 999999);

        $config = ModuleConfigFactory::create();
        $model = new SettingsForm($config, $this->createTranslator());
        $model->username = 'jane';
        $model->email = 'jane@example.com';

        $translator = $this->createTranslator();

        $data = AccountViewData::create(
            $user,
            $model,
            ['admin' => null],
            ['admin'],
            [],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame('jane', $data->usernameValue);
        self::assertStringContainsString('jane', $data->title);
        self::assertSame('//voyti/admin-users-update?id=999999', $data->formSubmitUrl);
        self::assertTrue($data->items[0]->checked);
        self::assertSame([], $data->errors);
    }
}
