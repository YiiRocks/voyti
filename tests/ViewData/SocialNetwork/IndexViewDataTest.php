<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\SocialNetwork;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\ViewData\SocialNetwork\IndexViewData;
use Yiisoft\Translator\Translator;

#[AllowMockObjectsWithoutExpectations]
final class IndexViewDataTest extends TestCase
{
    public function testCreateBuildsRowsAndConnectList(): void
    {
        $github = $this->createMock(AuthClientInterface::class);
        $github->method('getName')->willReturn('github');
        $github->method('getTitle')->willReturn('GitHub');

        $google = $this->createMock(AuthClientInterface::class);
        $google->method('getName')->willReturn('google');
        $google->method('getTitle')->willReturn('Google');

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');
        (new \ReflectionProperty(UserSocialAccount::class, 'id'))->setValue($account, 999999);

        $secondAccount = new UserSocialAccount();
        $secondAccount->setProvider('google');
        $secondAccount->setClientId('456');
        (new \ReflectionProperty(UserSocialAccount::class, 'id'))->setValue($secondAccount, 42);

        $data = IndexViewData::create(
            [$account, $secondAccount],
            new AuthClientRegistry($github, $google),
            ['github'],
            'voyti/session-auth',
            ModuleConfigFactory::create(),
            new FakeUrlGenerator(),
            new Translator('en', null, 'voyti'),
        );

        self::assertCount(2, $data->accounts);
        self::assertSame('GitHub', $data->accounts[0]->providerTitle);
        self::assertSame('//voyti/user-social-network-delete?id=999999', $data->accounts[0]->formSubmitUrl);
        self::assertSame('//voyti/user-social-network-delete?id=42', $data->accounts[1]->formSubmitUrl);
        self::assertCount(1, $data->connect->providers);
        self::assertSame('Google', $data->connect->providers[0]->title);
        self::assertNotEmpty($data->menu->items);
    }
}
