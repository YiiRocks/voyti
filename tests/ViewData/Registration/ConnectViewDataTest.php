<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Registration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Registration\ConnectViewData;

#[AllowMockObjectsWithoutExpectations]
final class ConnectViewDataTest extends TestCase
{
    public function testCreateResolvesProviderTitleAndUrls(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $client->method('getTitle')->willReturn('GitHub');

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');

        $data = ConnectViewData::create($account, new AuthClientRegistry($client), new FakeUrlGenerator());

        self::assertSame('GitHub', $data->providerTitle);
        self::assertSame('//voyti/session-login', $data->loginUrl);
        self::assertSame('//voyti/registration-register', $data->registerUrl);
    }
}
