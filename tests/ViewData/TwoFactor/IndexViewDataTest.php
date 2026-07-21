<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\ViewData\TwoFactor\IndexViewData;

final class IndexViewDataTest extends TestCase
{
    use TranslatorMockTrait;
    public function testCreateWhenEnabled(): void
    {
        $user = $this->createUser(authTfEnabled: true);

        $data = IndexViewData::create(
            $user,
            'google',
            ['code' => ['invalid']],
            '',
            null,
            false,
            true,
            true,
            new ModuleConfig(),
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

    public function testCreateWhenNotEnabledAndNotPreloadingSetsAutoloadUrl(): void
    {
        $user = $this->createUser(authTfEnabled: false);

        $data = IndexViewData::create(
            $user,
            'email',
            [],
            '',
            null,
            false,
            false,
            false,
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertNull($data->emailSetup);
        self::assertNull($data->googleSetup);
        self::assertSame('//voyti/user-two-factor-email', $data->autoloadUrl);
    }

    public function testCreateWhenNotEnabledAndPreloadingEmail(): void
    {
        $user = $this->createUser(authTfEnabled: false);

        $data = IndexViewData::create(
            $user,
            'email',
            [],
            '',
            null,
            true,
            false,
            true,
            new ModuleConfig(),
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
        $user = $this->createUser(authTfEnabled: false);

        $data = IndexViewData::create(
            $user,
            'google',
            [],
            '<svg></svg>',
            'ABC',
            false,
            false,
            true,
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertNotNull($data->googleSetup);
        self::assertNull($data->emailSetup);
        self::assertSame('<svg></svg>', $data->googleSetup->qrCodeUri);
    }

    private function createUser(bool $authTfEnabled): User
    {
        $user = new User();
        $user->setUsername('jane');
        $user->setEmail('jane@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setAuthTfEnabled($authTfEnabled);

        return $user;
    }


}
