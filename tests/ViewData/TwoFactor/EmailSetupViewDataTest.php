<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\TwoFactor\EmailSetupViewData;

final class EmailSetupViewDataTest extends TestCase
{
    public function testCreateAssignsUserEmailAndUrls(): void
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
}
