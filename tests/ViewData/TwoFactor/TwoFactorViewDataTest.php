<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\TwoFactor;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\TwoFactor\BackupCodesViewData;
use YiiRocks\Voyti\ViewData\TwoFactor\EmailSetupViewData;
use YiiRocks\Voyti\ViewData\TwoFactor\GoogleSetupViewData;
use YiiRocks\Voyti\ViewData\TwoFactor\IndexViewData;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;

final class TwoFactorViewDataTest extends TestCase
{
    use TranslatorMockTrait;
    use UserFactoryTrait;

    public function testBackupCodesCreateAssignsCodesAndContinueUrl(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = BackupCodesViewData::create(['aaa', 'bbb'], VoytiConfigFactory::create(), new FakeUrlGenerator(), $translator);

        self::assertSame(['aaa', 'bbb'], $data->codes);
        self::assertSame('//voyti/user-two-factor', $data->continueUrl);
        self::assertNotEmpty($data->menu->items);
    }

    public function testEmailSetupCreateAssignsUserEmailAndUrls(): void
    {
        $user = new User();
        $user->setUsername('jane');
        $user->setEmail('jane@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        $data = EmailSetupViewData::create($user, true, new FakeUrlGenerator());

        self::assertTrue($data->emailCodeSent);
        self::assertSame('jane@example.com', $data->userEmail);
        self::assertSame('//voyti/user-two-factor-email-send-code', $data->sendCodeUrl);
        self::assertSame('//voyti/user-two-factor-enable', $data->enableUrl);
    }

    public function testGoogleSetupCreateAssignsQrCodeAndSecret(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = GoogleSetupViewData::create('<svg></svg>', 'ABC123', new FakeUrlGenerator(), $translator);

        self::assertSame('<svg></svg>', $data->qrCodeUri);
        self::assertSame('ABC123', $data->secret);
        self::assertSame('//voyti/user-two-factor-enable', $data->formSubmitUrl);
        self::assertSame('voyti.view.two_factor.renew', $data->renewLabel);
        self::assertSame('voyti.view.two_factor.manual_entry', $data->manualEntryLabel);
    }

    public function testIndexCreateActiveMethodNamesTheMatchingLabel(): void
    {
        // A real translator (with placeholder substitution) makes the method-name ternary observable:
        // 'google' must resolve to the Google Authenticator label, not the email one.
        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(new CategorySource(
            'voyti',
            new MessageSource(dirname(__DIR__, 3) . '/resources/messages'),
            new SimpleMessageFormatter(),
        ));

        $data = IndexViewData::create(
            $this->buildUser(authTfEnabled: true),
            'google',
            [],
            '',
            null,
            false,
            true,
            true,
            true,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertStringContainsString('Google Authenticator', $data->enabledWithMethodMessage);
        self::assertStringNotContainsString('via email', $data->enabledWithMethodMessage);
    }

    public function testIndexCreateWhenEnabled(): void
    {
        $user = $this->buildUser(authTfEnabled: true);

        $data = IndexViewData::create(
            $user,
            'google',
            ['code' => ['invalid']],
            '',
            null,
            false,
            true,
            true,
            true,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertTrue($data->isEnabled);
        self::assertSame(['code' => ['invalid']], $data->errors);
        self::assertNull($data->emailSetup);
        self::assertNull($data->googleSetup);
        self::assertTrue($data->hasBackupCodes);
        self::assertSame('//voyti/user-two-factor-disable', $data->disableUrl);
    }

    public function testIndexCreateWhenGoogleUnavailableOmitsGoogleAndRenewUrls(): void
    {
        $user = $this->buildUser(authTfEnabled: false);

        $data = IndexViewData::create(
            $user,
            'email',
            [],
            '',
            null,
            false,
            false,
            false,
            false,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertNull($data->googleUrl);
        self::assertNull($data->renewUrl);
        self::assertSame('//voyti/user-two-factor-email', $data->emailUrl);
    }

    public function testIndexCreateWhenNotEnabledAndNotPreloadingSetsAutoloadUrl(): void
    {
        $user = $this->buildUser(authTfEnabled: false);

        $data = IndexViewData::create(
            $user,
            'email',
            [],
            '',
            null,
            false,
            false,
            false,
            true,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertNull($data->emailSetup);
        self::assertNull($data->googleSetup);
        self::assertSame('//voyti/user-two-factor-email', $data->autoloadUrl);
    }

    public function testIndexCreateWhenNotEnabledAndPreloadingEmail(): void
    {
        $user = $this->buildUser(authTfEnabled: false);

        $data = IndexViewData::create(
            $user,
            'email',
            [],
            '',
            null,
            true,
            false,
            true,
            true,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertFalse($data->isEnabled);
        self::assertNotNull($data->emailSetup);
        self::assertNull($data->googleSetup);
        self::assertTrue($data->emailSetup->emailCodeSent);
        self::assertNull($data->autoloadUrl);
    }
}
