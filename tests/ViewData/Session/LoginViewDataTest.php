<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Session;

use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Session\LoginViewData;
use Yiisoft\Yii\AuthClient\Collection;

final class LoginViewDataTest extends TestCase
{
    public function testCreateWithRegistrationDisabled(): void
    {
        $config = ModuleConfigFactory::create(enableRegistration: false);
        $form = new LoginForm($config, $this->createTranslator());

        // null clientCollection: yiisoft/yii-auth-client (optional) isn't installed.
        $data = LoginViewData::create($form, $config, new FakeUrlGenerator(), null);

        self::assertFalse($data->showRegisterLink);
        self::assertSame([], $data->connect->providers);
    }

    public function testCreateWithRegistrationEnabled(): void
    {
        $config = ModuleConfigFactory::create(enableRegistration: true);
        $form = new LoginForm($config, $this->createTranslator());

        $data = LoginViewData::create($form, $config, new FakeUrlGenerator(), new Collection([]));

        self::assertTrue($data->showRegisterLink);
        self::assertSame('//voyti/registration-register', $data->registerUrl);
        self::assertSame('//voyti/session-login', $data->formSubmitUrl);
        self::assertSame('//voyti/password-reset-request', $data->forgotPasswordUrl);
        self::assertSame('', $data->recaptchaFieldHtml);
        self::assertSame([], $data->connect->providers);
    }
}
