<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Registration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Registration\ConnectViewData;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;

#[AllowMockObjectsWithoutExpectations]
final class ConnectViewDataTest extends TestCase
{
    public function testCreateFallsBackToProviderKeyWhenClientCollectionIsNull(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');

        $data = ConnectViewData::create($account, null, new FakeUrlGenerator());

        self::assertSame('github', $data->providerTitle);
    }
    public function testCreateResolvesProviderTitleAndUrls(): void
    {
        $client = $this->createMock(OAuth2::class);
        $client->method('getTitle')->willReturn('GitHub');

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');

        $data = ConnectViewData::create(
            $account,
            new Collection(['github' => $client]),
            new FakeUrlGenerator(),
        );

        self::assertSame('GitHub', $data->providerTitle);
        self::assertSame('//voyti/session-login', $data->loginUrl);
        self::assertSame('//voyti/registration-register', $data->registerUrl);
    }
}
