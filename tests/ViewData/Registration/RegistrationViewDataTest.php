<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Registration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Registration\ConnectViewData;
use YiiRocks\Voyti\ViewData\Registration\RegisterViewData;
use YiiRocks\Voyti\ViewData\Registration\ResendViewData;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationViewDataTest extends TestCase
{
    public function testConnectCreateFallsBackToProviderKeyWhenClientCollectionIsNull(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');

        $data = ConnectViewData::create($account, null, new FakeUrlGenerator());

        self::assertSame('github', $data->providerTitle);
    }

    public function testConnectCreateResolvesProviderTitleAndUrls(): void
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

    public function testRegisterCreateWithGdprComplianceDisabled(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: false);
        $form = new RegistrationForm($config, $this->createTranslator());

        $data = RegisterViewData::create($form, $config, new FakeUrlGenerator());

        self::assertFalse($data->showGdprConsent);
    }

    public function testResendCreateAssignsResendUrlAndRecaptchaHtml(): void
    {
        $config = VoytiConfigFactory::create();
        $form = new ResendForm($config, $this->createTranslator());

        $data = ResendViewData::create($form, $config, new FakeUrlGenerator());

        self::assertSame('//voyti/registration-resend', $data->formSubmitUrl);
        self::assertSame('', $data->recaptchaFieldHtml);
    }
}
