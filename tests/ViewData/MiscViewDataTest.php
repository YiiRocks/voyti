<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionProperty;
use YiiRocks\Voyti\Model\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\ViewData\PasswordReset\RequestViewData;
use YiiRocks\Voyti\ViewData\Profile\UpdateViewData;
use YiiRocks\Voyti\ViewData\Settings\IndexViewData as SettingsIndexViewData;
use YiiRocks\Voyti\ViewData\SocialNetwork\IndexViewData as SocialNetworkIndexViewData;
use Yiisoft\Translator\Translator;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;

#[AllowMockObjectsWithoutExpectations]
final class MiscViewDataTest extends DatabaseTestCase
{
    use TestContainerTrait;
    use UserFactoryTrait;

    public function testPasswordResetRequestCreateAssignsUrlsAndRecaptchaHtml(): void
    {
        $config = VoytiConfigFactory::create();
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);

        $data = RequestViewData::create($form, $config, new FakeUrlGenerator());

        self::assertSame('//voyti/password-reset-request', $data->formSubmitUrl);
        self::assertSame('//voyti/session-login', $data->loginUrl);
        self::assertSame('', $data->recaptchaFieldHtml);
    }

    public function testProfileUpdateCreate(): void
    {
        $user = $this->buildUser();

        $data = UpdateViewData::create(
            $user,
            new UserProfile(),
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        self::assertSame('//voyti/user-profile', $data->updateUrl);
        self::assertNotEmpty($data->timezoneOptions);
        self::assertSame('list-group list-group-flush', $data->profile->profilePreviewClass);
    }

    public function testSettingsIndexCreateUsesProfileNameAsDisplayNameWhenProfileExists(): void
    {
        $user = $this->createUser(username: 'hasprofileuser');
        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName('Jane Doe');
        $profile->save();

        $data = SettingsIndexViewData::create(VoytiConfigFactory::create(), new FakeUrlGenerator(), $this->createTranslator(), $user);

        self::assertSame('Jane Doe', $data->displayName);
    }

    public function testSocialNetworkIndexCreateBuildsRowsAndConnectList(): void
    {
        $github = $this->createMock(OAuth2::class);
        $github->method('getTitle')->willReturn('GitHub');

        $google = $this->createMock(OAuth2::class);
        $google->method('getTitle')->willReturn('Google');

        $clientCollection = new Collection(['github' => $github, 'google' => $google]);
        // Overriding Collection::class re-initializes WidgetFactory against a container that
        // resolves AuthChoice::widget() with these specific test clients.
        $this->getTestContainer([Collection::class => $clientCollection]);

        $account = $this->buildSocialAccount('github', '123', 999999);
        $secondAccount = $this->buildSocialAccount('google', '456', 42);

        $data = SocialNetworkIndexViewData::create(
            [$account, $secondAccount],
            $clientCollection,
            ['github'],
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            new Translator('en', null, 'voyti'),
        );

        self::assertCount(2, $data->accounts);
        self::assertSame('GitHub', $data->accounts[0]->providerTitle);
        self::assertSame('//voyti/user-social-network-delete?id=999999', $data->accounts[0]->formSubmitUrl);
        self::assertSame('//voyti/user-social-network-delete?id=42', $data->accounts[1]->formSubmitUrl);
        self::assertSame(['google'], array_keys($data->authChoice?->getClients() ?? []));
        self::assertNotEmpty($data->menu->items);
    }

    public function testSocialNetworkIndexCreateFallsBackToProviderKeyAndEmptyConnectListWhenClientCollectionIsNull(): void
    {
        $account = $this->buildSocialAccount('github', '123', 999999);

        $data = SocialNetworkIndexViewData::create(
            [$account],
            null,
            [],
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            new Translator('en', null, 'voyti'),
        );

        self::assertSame('github', $data->accounts[0]->providerTitle);
        self::assertNull($data->authChoice);
    }

    private function buildSocialAccount(string $provider, string $clientId, int $id): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        (new ReflectionProperty(UserSocialAccount::class, 'id'))->setValue($account, $id);

        return $account;
    }
}
