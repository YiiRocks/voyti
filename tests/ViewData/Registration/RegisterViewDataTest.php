<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Registration;

use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Registration\RegisterViewData;

final class RegisterViewDataTest extends TestCase
{
    public function testCreateWithGdprComplianceDisabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: false);
        $form = new RegistrationForm($config, $this->createTranslator());

        $data = RegisterViewData::create($form, $config, new FakeUrlGenerator());

        self::assertFalse($data->showGdprConsent);
    }

    public function testCreateWithGdprComplianceEnabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $form = new RegistrationForm($config, $this->createTranslator());

        $data = RegisterViewData::create($form, $config, new FakeUrlGenerator());

        self::assertTrue($data->showGdprConsent);
        self::assertSame('//voyti/registration-register', $data->formSubmitUrl);
        self::assertSame('//voyti/session-login', $data->loginUrl);
        self::assertSame('', $data->recaptchaFieldHtml);
    }
}
