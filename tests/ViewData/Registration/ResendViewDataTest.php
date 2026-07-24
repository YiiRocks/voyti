<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Registration;

use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Registration\ResendViewData;

final class ResendViewDataTest extends TestCase
{
    public function testCreateAssignsResendUrlAndRecaptchaHtml(): void
    {
        $config = ModuleConfigFactory::create();
        $form = new ResendForm($config, $this->createTranslator());

        $data = ResendViewData::create($form, $config, new FakeUrlGenerator());

        self::assertSame('//voyti/registration-resend', $data->formSubmitUrl);
        self::assertSame('', $data->recaptchaFieldHtml);
    }
}
