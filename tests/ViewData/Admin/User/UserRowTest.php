<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\ViewData\Admin\User\UserRow;

final class UserRowTest extends TestCase
{
    use TranslatorMockTrait;
    use UserFactoryTrait;
    public function testCreateBuildsBlockToggleLabel(): void
    {
        $user = $this->buildUser();

        $row = UserRow::create($user, new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('voyti.view.block_button', $row->blockToggleLabel);

        $user->setBlockedAt(time());
        $blockedRow = UserRow::create($user, new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('voyti.view.unblock_button', $blockedRow->blockToggleLabel);
    }

    public function testCreateBuildsUrlsScopedToUserId(): void
    {
        $row = UserRow::create($this->buildUser(), new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('//voyti/admin-users-show?id=0', $row->showUrl);
        self::assertSame('//voyti/admin-users-update?id=0', $row->updateUrl);
        self::assertSame('//voyti/admin-users-update-profile?id=0', $row->updateProfileUrl);
        self::assertSame('//voyti/admin-users-sessions?id=0', $row->sessionsUrl);
        self::assertSame('//voyti/admin-users-confirm?id=0', $row->confirmUrl);
        self::assertSame('//voyti/admin-users-force-password-change?id=0', $row->forcePasswordChangeUrl);
        self::assertSame('//voyti/admin-users-password-reset?id=0', $row->passwordResetUrl);
        self::assertSame('//voyti/admin-users-switch-identity?id=0', $row->switchIdentityUrl);
        self::assertSame('//voyti/admin-users-block?id=0', $row->blockToggleUrl);
        self::assertSame('//voyti/admin-users-delete?id=0', $row->deleteUrl);
    }

    public function testCreateDisablesSwitchIdentityForSelf(): void
    {
        $user = $this->buildUser();

        $config = new ModuleConfig(enableSwitchIdentities: true);
        $row = UserRow::create($user, $config, new FakeUrlGenerator(), $this->createTranslator(), false, $user->getIdOrZero());

        self::assertTrue($row->switchIdentityDisabled);
    }

    public function testCreateHidesForcePasswordChangeWhenDisabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false);

        $row = UserRow::create($this->buildUser(), $config, new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertFalse($row->showForcePasswordChangeAction);
    }

    public function testCreateHidesSwitchIdentityWhenAlreadySwitched(): void
    {
        $config = new ModuleConfig(enableSwitchIdentities: true);

        $row = UserRow::create($this->buildUser(), $config, new FakeUrlGenerator(), $this->createTranslator(), true, 999999);

        self::assertFalse($row->showSwitchIdentityAction);
    }

    public function testCreateResolvesBlockedStatus(): void
    {
        $user = $this->buildUser();
        $user->setBlockedAt(time());

        $row = UserRow::create($user, new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('voyti.view.status_blocked', $row->statusLabel);
        self::assertSame('bg-danger', $row->statusBadgeClass);
        self::assertTrue($row->showConfirmAction);
    }

    public function testCreateResolvesConfirmedStatus(): void
    {
        $user = $this->buildUser();
        $user->setConfirmedAt(time());

        $row = UserRow::create($user, new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('voyti.view.status_active', $row->statusLabel);
        self::assertFalse($row->showConfirmAction);
    }

    public function testCreateResolvesPendingStatus(): void
    {
        $row = UserRow::create($this->buildUser(), new ModuleConfig(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('voyti.view.status_pending', $row->statusLabel);
        self::assertTrue($row->showConfirmAction);
    }

    public function testCreateShowsForcePasswordChangeWhenEnabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);

        $row = UserRow::create($this->buildUser(), $config, new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertTrue($row->showForcePasswordChangeAction);
    }

    public function testCreateShowsSwitchIdentityWhenEnabledAndNotSwitched(): void
    {
        $config = new ModuleConfig(enableSwitchIdentities: true);

        $row = UserRow::create($this->buildUser(), $config, new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertTrue($row->showSwitchIdentityAction);
    }

}
