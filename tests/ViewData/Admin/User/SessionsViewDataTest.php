<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\User\SessionsViewData;
use Yiisoft\Translator\Translator;

final class SessionsViewDataTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testCreateFormatsSessionTimesInViewerTimezoneNotTargetUserTimezone(): void
    {
        $user = $this->createUser(username: 'jane', email: 'jane@example.com');
        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setTimezone('America/New_York');
        $profile->save();

        $updatedAt = time();
        $session = new UserSessions();
        $session->setUserId((int) $user->getId());
        $session->setSessionId('abc');
        $session->setIp('203.0.113.1');
        $session->setUserAgent('curl');
        $session->setCreatedAt($updatedAt);
        $session->setUpdatedAt($updatedAt);

        $translator = new Translator('en', null, 'voyti');

        $data = SessionsViewData::create($user, [$session], new FakeUrlGenerator(), $translator, 'Asia/Tokyo');

        self::assertSame(
            TimezoneHelper::formatLocalized($updatedAt, 'en', 'Asia/Tokyo'),
            $data->sessions[0]->lastSeenDisplay,
        );
    }

    public function testCreateMapsSessionsAndBuildsTerminateAllUrl(): void
    {
        $user = $this->createUser(username: 'jane', email: 'jane@example.com');

        $session = new UserSessions();
        $session->setUserId((int) $user->getId());
        $session->setSessionId('abc');
        $session->setIp('203.0.113.1');
        $session->setUserAgent('curl');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());

        $translator = new Translator('en', null, 'voyti');

        $data = SessionsViewData::create($user, [$session], new FakeUrlGenerator(), $translator, null);

        self::assertCount(1, $data->sessions);
        self::assertSame('203.0.113.1', $data->sessions[0]->ip);
        self::assertSame('//voyti/admin-users-terminate-sessions?id=' . $user->getId(), $data->formSubmitUrl);
    }
}
