<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\SocialNetwork;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\ViewData\SocialNetwork\IndexViewData;
use Yiisoft\Translator\Translator;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;

#[AllowMockObjectsWithoutExpectations]
final class IndexViewDataTest extends TestCase
{
    use TestContainerTrait;

    public function testCreateBuildsRowsAndConnectList(): void
    {
        $github = $this->createMock(OAuth2::class);
        $github->method('getTitle')->willReturn('GitHub');

        $google = $this->createMock(OAuth2::class);
        $google->method('getTitle')->willReturn('Google');

        $clientCollection = new Collection(['github' => $github, 'google' => $google]);
        // Overriding Collection::class re-initializes WidgetFactory against a container that
        // resolves AuthChoice::widget() with these specific test clients.
        $this->getTestContainer([Collection::class => $clientCollection]);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');
        (new ReflectionProperty(UserSocialAccount::class, 'id'))->setValue($account, 999999);

        $secondAccount = new UserSocialAccount();
        $secondAccount->setProvider('google');
        $secondAccount->setClientId('456');
        (new ReflectionProperty(UserSocialAccount::class, 'id'))->setValue($secondAccount, 42);

        $data = IndexViewData::create(
            [$account, $secondAccount],
            $clientCollection,
            ['github'],
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            new Translator('en', null, 'voyti'),
            false,
            null,
        );

        self::assertCount(2, $data->accounts);
        self::assertSame('GitHub', $data->accounts[0]->providerTitle);
        self::assertSame('//voyti/user-social-network-delete?id=999999', $data->accounts[0]->formSubmitUrl);
        self::assertSame('//voyti/user-social-network-delete?id=42', $data->accounts[1]->formSubmitUrl);
        self::assertSame(['google'], array_keys($data->authChoice?->getClients() ?? []));
        self::assertNotEmpty($data->menu->items);
    }

    public function testCreateFallsBackToProviderKeyAndEmptyConnectListWhenClientCollectionIsNull(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');
        (new ReflectionProperty(UserSocialAccount::class, 'id'))->setValue($account, 999999);

        $data = IndexViewData::create(
            [$account],
            null,
            [],
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            new Translator('en', null, 'voyti'),
            false,
            null,
        );

        self::assertSame('github', $data->accounts[0]->providerTitle);
        self::assertNull($data->authChoice);
    }
}
