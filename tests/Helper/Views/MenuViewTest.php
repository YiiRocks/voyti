<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper\Views;

use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;

final class MenuViewTest extends TestCase
{
    public function testAccountBuildsCoreAndPackageLinks(): void
    {
        $menu = MenuView::account(
            VoytiConfigFactory::create(accountMenuItems: [
                ['label' => 'Two-Factor', 'category' => 'app', 'route' => 'app/two-factor'],
            ]),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        $labels = array_column($menu, 'label');
        self::assertContains('Dashboard', $labels);
        self::assertContains('Account', $labels);
        self::assertContains('Two-Factor', $labels);

        // Non-logout links keep the default alignEnd=false
        self::assertFalse($menu[0]['alignEnd']);
        self::assertSame(null, $menu[0]['routeName']);

        $logout = $menu[array_key_last($menu)];
        self::assertSame('Log out', $logout['label']);
        self::assertTrue($logout['alignEnd']);
        self::assertSame('voyti/session-logout', $logout['routeName']);
    }

    public function testAccountIncludesPrivacyLinkWhenAccountDeleteAllowed(): void
    {
        $menu = MenuView::account(
            VoytiConfigFactory::create(allowAccountDelete: true),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertContains('Privacy', array_column($menu, 'label'));
    }

    public function testAccountIncludesPrivacyLinkWhenPrivacyMenuItemsContributed(): void
    {
        $menu = MenuView::account(
            VoytiConfigFactory::create(
                allowAccountDelete: false,
                privacyMenuItems: [
                    ['label' => 'voyti.view.privacy.title', 'category' => 'voyti', 'route' => 'voyti/user-privacy'],
                ],
            ),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertContains('Privacy', array_column($menu, 'label'));
    }

    public function testAccountOmitsPrivacyLinkWhenAccountDeleteNotAllowedAndNoPrivacyMenuItems(): void
    {
        $menu = MenuView::account(
            VoytiConfigFactory::create(allowAccountDelete: false, privacyMenuItems: []),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        $labels = array_column($menu, 'label');
        self::assertNotContains('Privacy', $labels);
    }

    public function testAdminBuildsAdminLinks(): void
    {
        $menu = MenuView::admin(new FakeUrlGenerator(), $this->createTranslator());

        self::assertContains('Dashboard', array_column($menu, 'label'));
        self::assertContains('Roles', array_column($menu, 'label'));
        self::assertContains('Permissions', array_column($menu, 'label'));
        self::assertTrue($menu[array_key_last($menu)]['alignEnd']);
    }
}
