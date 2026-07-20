<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

final class MenuViewDataTest extends TestCase
{
    public function testForAccountIncludesPrivacyWhenAccountDeleteAllowedWithoutGdpr(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: false, allowAccountDelete: true);

        $menu = MenuViewData::forAccount($config, new FakeUrlGenerator(), $this->translator());

        $labels = array_map(static fn($item) => $item->label, $menu->items);

        self::assertContains('voyti.view.settings.privacy', $labels);
    }

    public function testForAccountIncludesTwoFactorAndPrivacyWhenEnabled(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true, enableGdprCompliance: true);

        $menu = MenuViewData::forAccount($config, new FakeUrlGenerator(), $this->translator());

        $labels = array_map(static fn($item) => $item->label, $menu->items);

        self::assertContains('voyti.menu.two_factor', $labels);
        self::assertContains('voyti.view.settings.privacy', $labels);
    }

    public function testForAccountOmitsOptionalItemsWhenDisabled(): void
    {
        $menu = MenuViewData::forAccount(new ModuleConfig(), new FakeUrlGenerator(), $this->translator());

        $labels = array_map(static fn($item) => $item->label, $menu->items);

        self::assertSame([
            'voyti.menu.userProfile',
            'voyti.menu.account',
            'voyti.menu.networks',
            'voyti.menu.sessions',
            'voyti.menu.logout',
        ], $labels);
        self::assertTrue($menu->items[array_key_last($menu->items)]->alignEnd);
        self::assertFalse($menu->items[0]->alignEnd);
    }

    public function testForAdminBuildsFixedMenu(): void
    {
        $menu = MenuViewData::forAdmin(new FakeUrlGenerator(), $this->translator());

        $labels = array_map(static fn($item) => $item->label, $menu->items);

        self::assertSame([
            'voyti.view.dashboard.title',
            'voyti.view.admin.title',
            'voyti.view.role.title',
            'voyti.view.permission.title',
            'voyti.view.rule.title',
            'voyti.view.audit_log.title',
            'voyti.menu.logout',
        ], $labels);
        self::assertTrue($menu->items[array_key_last($menu->items)]->alignEnd);
    }

    private function translator(): TranslatorInterface
    {
        return new Translator('en', null, 'voyti');
    }
}
