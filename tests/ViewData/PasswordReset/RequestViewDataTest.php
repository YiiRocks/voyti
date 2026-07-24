<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\PasswordReset;

use YiiRocks\Voyti\Model\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\PasswordReset\RequestViewData;

final class RequestViewDataTest extends TestCase
{
    public function testCreateAssignsUrlsAndRecaptchaHtml(): void
    {
        $config = ModuleConfigFactory::create();
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);

        $data = RequestViewData::create($form, $config, new FakeUrlGenerator());

        self::assertSame('//voyti/password-reset-request', $data->formSubmitUrl);
        self::assertSame('//voyti/session-login', $data->loginUrl);
        self::assertSame('', $data->recaptchaFieldHtml);
    }
}
