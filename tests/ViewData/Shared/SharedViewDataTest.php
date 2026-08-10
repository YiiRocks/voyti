<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\ViewData\Shared\ProfileCardViewData;
use YiiRocks\Voyti\ViewData\Shared\SessionRow;
use Yiisoft\Session\Flash\FlashInterface;

#[AllowMockObjectsWithoutExpectations]
final class SharedViewDataTest extends TestCase
{
    use TranslatorMockTrait;
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

    public function testFlashFromFlashCastsNonStringMessageToString(): void
    {
        // A non-string flash value must be coerced to string (the property is typed ?string).
        $flash = $this->createMock(FlashInterface::class);
        $flash->method('get')->willReturnMap([
            [FlashType::WARNING, 42],
            [FlashType::SUCCESS, 99],
        ]);

        $data = new FlashViewData($flash);

        self::assertSame('42', $data->warning);
        self::assertSame('99', $data->success);
    }

    public function testMenuForAccountIncludesPrivacyWhenAccountDeleteAllowedWithoutGdpr(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: false, allowAccountDelete: true);

        $menu = MenuViewData::forAccount($config, new FakeUrlGenerator(), $this->createTranslator());

        $labels = array_map(static fn($item) => $item->label, $menu->items);

        self::assertContains('voyti.view.settings.privacy', $labels);
    }

    public function testMenuForAccountIncludesTwoFactorAndPrivacyWhenEnabled(): void
    {
        $config = VoytiConfigFactory::create(enableTwoFactorAuthentication: true, enableGdprCompliance: true);

        $menu = MenuViewData::forAccount($config, new FakeUrlGenerator(), $this->createTranslator());

        $labels = array_map(static fn($item) => $item->label, $menu->items);

        self::assertContains('voyti.menu.two_factor', $labels);
        self::assertContains('voyti.view.settings.privacy', $labels);
    }

    public function testMenuForAccountOmitsOptionalItemsWhenDisabled(): void
    {
        $menu = MenuViewData::forAccount(VoytiConfigFactory::create(), new FakeUrlGenerator(), $this->createTranslator());

        $labels = array_map(static fn($item) => $item->label, $menu->items);

        self::assertSame([
            'voyti.menu.dashboard',
            'voyti.menu.userProfile',
            'voyti.menu.account',
            'voyti.menu.networks',
            'voyti.menu.sessions',
            'voyti.menu.logout',
        ], $labels);
        self::assertTrue($menu->items[array_key_last($menu->items)]->alignEnd);
        self::assertFalse($menu->items[0]->alignEnd);
    }

    public function testMenuForAdminBuildsFixedMenu(): void
    {
        $menu = MenuViewData::forAdmin(new FakeUrlGenerator(), $this->createTranslator());

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

    public function testProfileCardCreateFormatsRegisteredDisplayInViewerTimezoneNotProfileTimezone(): void
    {
        $createdAt = time();
        $user = $this->buildUser();
        $user->setCreatedAt($createdAt);
        $profile = $this->createProfile();
        $profile->setTimezone('America/New_York');

        $translator = $this->createTranslator();

        $data = ProfileCardViewData::create(
            $user,
            $profile,
            $translator,
            showAdminFields: true,
            viewerTimezone: 'Asia/Tokyo',
        );

        self::assertSame(
            TimezoneHelper::formatLocalized($createdAt, $translator->getLocale(), 'Asia/Tokyo'),
            $data->registeredDisplay,
        );
    }

    public function testProfileCardCreatePassesThroughProfileFieldsAndCustomPreviewClass(): void
    {
        $profile = $this->createProfile();
        $profile->setPublicEmail('public@example.com');
        $profile->setLocation('Warsaw');
        $profile->setWebsite('https://example.com');
        $profile->setTimezone('Europe/Warsaw');
        $profile->setBio('hello');
        $profile->setGravatarEmail('test@example.com');

        $data = ProfileCardViewData::create(
            $this->buildUser(),
            $profile,
            $this->createTranslator(),
            profilePreviewClass: 'list-group list-group-flush',
        );

        self::assertSame('public@example.com', $data->publicEmail);
        self::assertSame('Warsaw', $data->location);
        self::assertSame('https://example.com', $data->website);
        self::assertSame('Europe/Warsaw', $data->timezone);
        self::assertSame('hello', $data->bio);
        self::assertNotNull($data->gravatarUrl);
        self::assertSame('list-group list-group-flush', $data->profilePreviewClass);
    }

    public function testProfileCardCreateUsesProfileNameWhenSet(): void
    {
        $user = $this->buildUser(username: 'janedoe');
        $profile = $this->createProfile();
        $profile->setName('Jane Doe');

        $data = ProfileCardViewData::create($user, $profile, $this->createTranslator());

        self::assertSame('Jane Doe', $data->displayName);
    }

    public function testProfileCardCreateWithAdminFieldsResolvesBlockedStatus(): void
    {
        $user = $this->buildUser();
        $user->setBlockedAt(time());

        $data = ProfileCardViewData::create($user, $this->createProfile(), $this->createTranslator(), showAdminFields: true);

        self::assertTrue($data->showAdminFields);
        self::assertSame('voyti.view.status_blocked', $data->statusLabel);
        self::assertSame('bg-danger', $data->statusBadgeClass);
        self::assertSame($user->getEmail(), $data->email);
        self::assertNotNull($data->registeredDisplay);
    }

    public function testProfileCardCreateWithAdminFieldsResolvesConfirmedStatus(): void
    {
        $user = $this->buildUser();
        $user->setConfirmedAt(time());

        $data = ProfileCardViewData::create($user, $this->createProfile(), $this->createTranslator(), showAdminFields: true);

        self::assertSame('voyti.view.status_active', $data->statusLabel);
        self::assertSame('bg-success', $data->statusBadgeClass);
    }

    public function testProfileCardCreateWithoutAdminFieldsLeavesAdminOnlyFieldsNull(): void
    {
        $user = $this->buildUser();
        $profile = $this->createProfile();

        $data = ProfileCardViewData::create($user, $profile, $this->createTranslator());

        self::assertFalse($data->showAdminFields);
        self::assertNull($data->email);
        self::assertNull($data->registeredDisplay);
        self::assertNull($data->statusLabel);
        self::assertNull($data->statusBadgeClass);
        self::assertSame('list-group mb-4', $data->profilePreviewClass);
    }

    public function testSessionRowCreateWhenActive(): void
    {
        $session = $this->buildUserSession();

        $row = SessionRow::create($session, 'UTC', 'en');

        self::assertSame('203.0.113.1', $row->ip);
        self::assertSame('curl', $row->userAgent);
        self::assertNotEmpty($row->lastSeenDisplay);
        self::assertFalse($row->isRevoked);
        self::assertNull($row->revokedAtDisplay);
    }

    private function createProfile(): UserProfile
    {
        return new UserProfile();
    }
}
