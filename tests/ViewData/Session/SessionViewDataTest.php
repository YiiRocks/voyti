<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Session;

use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Session\LoginViewData;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;

final class SessionViewDataTest extends TestCase
{
    use TestContainerTrait;

    public function testLoginCreateWithRegistrationDisabled(): void
    {
        $config = VoytiConfigFactory::create(enableRegistration: false);
        $form = new LoginForm($config, $this->createTranslator());

        $data = LoginViewData::create($form, $config, new FakeUrlGenerator(), null);

        self::assertFalse($data->showRegisterLink);
        self::assertNull($data->authChoice);
    }

    public function testLoginCreateWithRegistrationEnabled(): void
    {
        $this->getTestContainer();

        $config = VoytiConfigFactory::create(enableRegistration: true);
        $form = new LoginForm($config, $this->createTranslator());
        $url = new FakeUrlGenerator();

        $data = LoginViewData::create($form, $config, $url, new Collection([]));

        self::assertTrue($data->showRegisterLink);
        self::assertSame('//voyti/registration-register', $data->registerUrl);
        self::assertSame('//voyti/session-login', $data->formSubmitUrl);
        self::assertSame('//voyti/password-reset-request', $data->forgotPasswordUrl);
        self::assertSame('', $data->recaptchaFieldHtml);
        self::assertInstanceOf(AuthChoice::class, $data->authChoice);
    }
}
