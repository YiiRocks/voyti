<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\ViewData\Shared\ProfileCardViewData;

final class ProfileCardViewDataTest extends TestCase
{
    use TranslatorMockTrait;
    use UserFactoryTrait;
    public function testCreateFormatsRegisteredDisplayInViewerTimezoneNotProfileTimezone(): void
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

    public function testCreatePassesThroughProfileFieldsAndCustomPreviewClass(): void
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

    public function testCreateUsesProfileNameWhenSet(): void
    {
        $user = $this->buildUser(username: 'janedoe');
        $profile = $this->createProfile();
        $profile->setName('Jane Doe');

        $data = ProfileCardViewData::create($user, $profile, $this->createTranslator());

        self::assertSame('Jane Doe', $data->displayName);
    }

    public function testCreateUsesUsernameWhenProfileNameIsNull(): void
    {
        $user = $this->buildUser(username: 'janedoe');
        $profile = $this->createProfile();

        $data = ProfileCardViewData::create($user, $profile, $this->createTranslator());

        self::assertSame('janedoe', $data->displayName);
    }

    public function testCreateWithAdminFieldsResolvesBlockedStatus(): void
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

    public function testCreateWithAdminFieldsResolvesConfirmedStatus(): void
    {
        $user = $this->buildUser();
        $user->setConfirmedAt(time());

        $data = ProfileCardViewData::create($user, $this->createProfile(), $this->createTranslator(), showAdminFields: true);

        self::assertSame('voyti.view.status_active', $data->statusLabel);
        self::assertSame('bg-success', $data->statusBadgeClass);
    }

    public function testCreateWithAdminFieldsResolvesPendingStatus(): void
    {
        $user = $this->buildUser();

        $data = ProfileCardViewData::create($user, $this->createProfile(), $this->createTranslator(), showAdminFields: true);

        self::assertSame('voyti.view.status_pending', $data->statusLabel);
        self::assertSame('bg-warning text-dark', $data->statusBadgeClass);
    }

    public function testCreateWithoutAdminFieldsLeavesAdminOnlyFieldsNull(): void
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

    private function createProfile(): UserProfile
    {
        return new UserProfile();
    }

}
