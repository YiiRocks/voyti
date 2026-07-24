<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\ViewData\TwoFactor\IndexViewData;

final class IndexViewDataTest extends TestCase
{
    use TranslatorMockTrait;
    use UserFactoryTrait;
    public function testCreateWhenEnabled(): void
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
            ModuleConfigFactory::create(),
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

    public function testCreateWhenGoogleUnavailableOmitsGoogleAndRenewUrls(): void
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
            ModuleConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertNull($data->googleUrl);
        self::assertNull($data->renewUrl);
        self::assertSame('//voyti/user-two-factor-email', $data->emailUrl);
    }

    public function testCreateWhenNotEnabledAndNotPreloadingSetsAutoloadUrl(): void
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
            ModuleConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertNull($data->emailSetup);
        self::assertNull($data->googleSetup);
        self::assertSame('//voyti/user-two-factor-email', $data->autoloadUrl);
    }

    public function testCreateWhenNotEnabledAndPreloadingEmail(): void
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
            ModuleConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertFalse($data->isEnabled);
        self::assertNotNull($data->emailSetup);
        self::assertNull($data->googleSetup);
        self::assertTrue($data->emailSetup->emailCodeSent);
        self::assertNull($data->autoloadUrl);
    }

    public function testCreateWhenNotEnabledAndPreloadingGoogle(): void
    {
        $user = $this->buildUser(authTfEnabled: false);

        $data = IndexViewData::create(
            $user,
            'google',
            [],
            '<svg></svg>',
            'ABC',
            false,
            false,
            true,
            true,
            ModuleConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertNotNull($data->googleSetup);
        self::assertNull($data->emailSetup);
        self::assertSame('<svg></svg>', $data->googleSetup->qrCodeUri);
    }

}
