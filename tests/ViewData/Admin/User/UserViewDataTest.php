<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use ReflectionProperty;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\ViewData\Admin\User\AccountViewData;
use YiiRocks\Voyti\ViewData\Admin\User\AssignmentsViewData;
use YiiRocks\Voyti\ViewData\Admin\User\CreateViewData;
use YiiRocks\Voyti\ViewData\Admin\User\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\User\InfoViewData;
use YiiRocks\Voyti\ViewData\Admin\User\ProfileViewData;
use YiiRocks\Voyti\ViewData\Admin\User\SessionsViewData;
use YiiRocks\Voyti\ViewData\Admin\User\UserRow;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Translator\Translator;

final class UserViewDataTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testAccountCreateBuildsTitleAndItems(): void
    {
        $user = $this->buildUserWithId('jane');
        $config = VoytiConfigFactory::create();
        $model = new SettingsForm($config, $this->createTranslator());
        $model->username = 'jane';
        $model->email = 'jane@example.com';

        $translator = $this->createTranslator();

        $data = AccountViewData::create(
            $user,
            $model,
            ['admin' => null],
            ['admin'],
            [],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame('jane', $data->usernameValue);
        self::assertStringContainsString('jane', $data->title);
        self::assertSame('//voyti/admin-users-update?id=999999', $data->formSubmitUrl);
        self::assertTrue($data->items[0]->checked);
        self::assertSame([], $data->errors);
    }

    public function testAssignmentsCreateSplitsAssignedAndAvailableNames(): void
    {
        $user = $this->buildUserWithId('jane');
        $translator = new Translator('en', null, 'voyti');

        $data = AssignmentsViewData::create(
            $user,
            ['admin'],
            ['editor' => null, 'viewer' => null],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame(['admin'], $data->assignedItemNames);
        self::assertSame(['editor', 'viewer'], $data->availableItemNames);
        self::assertSame('//voyti/admin-users-assignments?id=999999', $data->formSubmitUrl);
    }

    public function testCreateBuildsItemsAndCarriesFormValues(): void
    {
        $config = VoytiConfigFactory::create();
        $model = new RegistrationForm($config, $this->createTranslator());
        $model->username = 'jane';
        $model->email = 'jane@example.com';

        $translator = $this->createTranslator();

        $data = CreateViewData::create(
            $model,
            ['admin' => null, 'editor' => null],
            ['editor'],
            ['username' => ['taken']],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame('jane', $data->usernameValue);
        self::assertSame('jane@example.com', $data->emailValue);
        self::assertSame('//voyti/admin-users-create', $data->formSubmitUrl);
        self::assertCount(2, $data->items);
        self::assertTrue($data->items[1]->checked);
        self::assertSame(['username' => ['taken']], $data->errors);
        self::assertNotEmpty($data->menu->items);
    }

    public function testIndexCreate(): void
    {
        $user = $this->buildUser('jane');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(
            [$user],
            $paginator,
            ['username' => 'jane'],
            25,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
            false,
            999999,
        );

        self::assertCount(1, $data->users);
        self::assertSame('jane', $data->users[0]->username);
        self::assertSame(['username' => 'jane', 'email' => '', 'status' => ''], $data->filters);
        self::assertSame('//voyti/admin-users-create', $data->createUserUrl);
        self::assertSame(25, $data->perPage);
        self::assertStringContainsString('perPage=25', $data->pageUrlPattern);
        self::assertStringContainsString('perPage=25', $data->firstPageUrl);
    }

    public function testIndexCreateHidesSwitchIdentityActionWhenAlreadySwitched(): void
    {
        $user = $this->buildUser('jane');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = $this->createTranslator();

        $data = IndexViewData::create(
            [$user],
            $paginator,
            [],
            25,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
            true,
            999999,
        );

        self::assertFalse($data->users[0]->showSwitchIdentityAction);
    }

    public function testIndexCreatePreservesAllFiltersInNormalizedFiltersAndPageUrls(): void
    {
        $user = $this->buildUser('jane');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(
            [$user],
            $paginator,
            ['username' => 'jane', 'email' => 'jane2@example.com', 'status' => 'blocked'],
            25,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
            false,
            999999,
        );

        // Every submitted filter is normalized through, not dropped or blanked.
        self::assertSame(
            ['username' => 'jane', 'email' => 'jane2@example.com', 'status' => 'blocked'],
            $data->filters,
        );
        // The filters are carried into both paginated URLs so filtering survives pagination.
        foreach ([$data->pageUrlPattern, $data->firstPageUrl] as $pageUrl) {
            self::assertStringContainsString('username=jane', $pageUrl);
            self::assertStringContainsString('email=jane2%40example.com', $pageUrl);
            self::assertStringContainsString('status=blocked', $pageUrl);
            self::assertStringContainsString('perPage=25', $pageUrl);
        }
    }

    public function testInfoCreateShowsAdminFields(): void
    {
        $createdAt = time();
        $user = $this->buildUser('jane');
        $user->setCreatedAt($createdAt);
        $user->setUpdatedAt($createdAt);

        $profile = new UserProfile();
        $profile->setTimezone('America/New_York');

        $translator = new Translator('en', null, 'voyti');

        $data = InfoViewData::create($user, $profile, new FakeUrlGenerator(), $translator, 'Asia/Tokyo');

        self::assertSame('jane', $data->username);
        self::assertTrue($data->profile->showAdminFields);
        self::assertSame('list-group list-group-flush', $data->profile->profilePreviewClass);
        self::assertNotEmpty($data->menu->items);
        self::assertSame(
            TimezoneHelper::formatLocalized($createdAt, $translator->getLocale(), 'Asia/Tokyo'),
            $data->profile->registeredDisplay,
        );
    }

    public function testProfileCreateBuildsUpdateUrlAndTimezoneOptions(): void
    {
        $user = $this->buildUserWithId('jane');
        $translator = new Translator('en', null, 'voyti');

        $data = ProfileViewData::create($user, new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/admin-users-update-profile?id=999999', $data->formSubmitUrl);
        self::assertNotEmpty($data->timezoneOptions);
    }

    public function testSessionsCreateFormatsSessionTimesInViewerTimezoneNotTargetUserTimezone(): void
    {
        $user = $this->createUser(username: 'jane', email: 'jane@example.com');
        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setTimezone('America/New_York');
        $profile->save();

        $updatedAt = time();
        $session = $this->buildSessionForUser((int) $user->getId(), $updatedAt);

        $translator = new Translator('en', null, 'voyti');

        $data = SessionsViewData::create($user, [$session], new FakeUrlGenerator(), $translator, 'Asia/Tokyo');

        self::assertSame(
            TimezoneHelper::formatLocalized($updatedAt, 'en', 'Asia/Tokyo'),
            $data->sessions[0]->lastSeenDisplay,
        );
    }

    public function testSessionsCreateMapsSessionsAndBuildsTerminateAllUrl(): void
    {
        $user = $this->createUser(username: 'jane', email: 'jane@example.com');
        $session = $this->buildSessionForUser((int) $user->getId());

        $translator = new Translator('en', null, 'voyti');

        $data = SessionsViewData::create($user, [$session], new FakeUrlGenerator(), $translator, null);

        self::assertCount(1, $data->sessions);
        self::assertSame('203.0.113.1', $data->sessions[0]->ip);
        self::assertSame('//voyti/admin-users-terminate-sessions?id=' . $user->getId(), $data->formSubmitUrl);
    }

    public function testUserRowCreateBuildsBlockToggleLabel(): void
    {
        $user = $this->buildUser();

        $row = UserRow::create($user, VoytiConfigFactory::create(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('Block', $row->blockToggleLabel);

        $user->setBlockedAt(time());
        $blockedRow = UserRow::create($user, VoytiConfigFactory::create(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('Unblock', $blockedRow->blockToggleLabel);
    }

    public function testUserRowCreateBuildsUrlsScopedToUserId(): void
    {
        $row = UserRow::create($this->buildUser(), VoytiConfigFactory::create(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

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

    public function testUserRowCreateHidesForcePasswordChangeWhenDisabled(): void
    {
        $config = VoytiConfigFactory::create();

        $row = UserRow::create($this->buildUser(), $config, new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertFalse($row->showForcePasswordChangeAction);
    }

    public function testUserRowCreateResolvesBlockedStatus(): void
    {
        $user = $this->buildUser();
        $user->setBlockedAt(time());

        $row = UserRow::create($user, VoytiConfigFactory::create(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('Blocked', $row->statusLabel);
        self::assertSame('bg-danger', $row->statusBadgeClass);
        self::assertTrue($row->showConfirmAction);
    }

    public function testUserRowCreateResolvesConfirmedStatus(): void
    {
        $user = $this->buildUser();
        $user->setConfirmedAt(time());

        $row = UserRow::create($user, VoytiConfigFactory::create(), new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertSame('Active', $row->statusLabel);
        self::assertFalse($row->showConfirmAction);
    }

    public function testUserRowCreateShowsSwitchIdentityWhenEnabledAndNotSwitched(): void
    {
        $config = VoytiConfigFactory::create(enableSwitchIdentities: true);

        $row = UserRow::create($this->buildUser(), $config, new FakeUrlGenerator(), $this->createTranslator(), false, 999999);

        self::assertTrue($row->showSwitchIdentityAction);
    }

    private function buildSessionForUser(int $userId, ?int $updatedAt = null): UserSessions
    {
        $timestamp = $updatedAt ?? time();
        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId('abc');
        $session->setIp('203.0.113.1');
        $session->setUserAgent('curl');
        $session->setCreatedAt($timestamp);
        $session->setUpdatedAt($timestamp);

        return $session;
    }

    private function buildUserWithId(string $username): User
    {
        $user = $this->buildUser($username);
        (new ReflectionProperty(User::class, 'id'))->setValue($user, 999999);

        return $user;
    }
}
