<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\SocialNetwork;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use YiiRocks\Voyti\Controller\SocialNetwork\SocialNetworkController;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Assets\AssetBundle;
use Yiisoft\Assets\AssetLoaderInterface;
use Yiisoft\Assets\AssetPublisherInterface;
use Yiisoft\Assets\AssetUtil;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

#[AllowMockObjectsWithoutExpectations]
final class SocialNetworkControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
    }

    public function testDeleteWithFoundAccountDeletesAndRedirects(): void
    {
        $controller = $this->createController();

        // Another user's account is created first (lower id), so a lookup that ignored the id argument
        // would return this one instead of the target - it must not be touched.
        $otherAccount = $this->createSocialAccount(888888, provider: 'facebook', username: 'someoneelse');

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'));
        $this->currentUser->login($user);

        $account = $this->createSocialAccount((int) $user->getId());

        $result = $controller->delete($account->getId());

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('user-social-network', $result->getHeaderLine('Location'));
        // The targeted account is gone; the other user's account is untouched.
        $this->assertSame([], UserSocialAccount::findByUserId((int) $user->getId()));
        $this->assertNotNull(UserSocialAccount::query()->where(['id' => $otherAccount->getId()])->one());
    }

    public function testDeleteWithNoAccountShowsNotFound(): void
    {
        $controller = $this->createController();

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'));
        $this->currentUser->login($user);

        $html = (string) $controller->delete(999)->getBody();

        self::assertStringContainsString('Network not found', $html);
    }

    public function testIndexShowsConnectedAccounts(): void
    {
        $controller = $this->createController();

        $user = $this->createUser();
        $this->currentUser->login($user);
        $account = $this->createSocialAccount((int) $user->getId());

        $html = (string) $controller->index()->getBody();

        self::assertStringContainsString('Networks', $html);
        self::assertStringContainsString('github', $html);
        self::assertStringContainsString(
            '//voyti/user-social-network-delete?id=' . $account->getId(),
            $html,
        );
    }

    public function testIndexWithConfiguredClientsHidesConnectedProviders(): void
    {
        $github = $this->createMock(OAuth2Interface::class);
        $github->method('getName')->willReturn('github');
        $github->method('getTitle')->willReturn('GitHub');
        $google = $this->createMock(OAuth2Interface::class);
        $google->method('getName')->willReturn('google');
        $google->method('getTitle')->willReturn('Google');
        $collection = new Collection(['github' => $github, 'google' => $google]);

        $controller = $this->createController([
            Collection::class => $collection,
            ...$this->assetStubs(),
        ]);

        $user = $this->createUser();
        $this->currentUser->login($user);
        $this->createSocialAccount((int) $user->getId(), provider: 'github');

        $html = (string) $controller->index()->getBody();

        // The connected provider shows its title once (as the account row, not as a connect
        // button), while the unconnected provider is offered as a connect button.
        self::assertSame(1, substr_count($html, 'GitHub'));
        self::assertStringContainsString('Google', $html);
        self::assertStringContainsString('voyti/session-auth?authclient=google', $html);
    }

    public function testIndexWithNullClientCollectionFallsBackToProviderKey(): void
    {
        $controller = $this->createController([
            SocialNetworkController::class => [
                'class' => SocialNetworkController::class,
                '__construct()' => ['clientCollection' => null],
            ],
        ]);

        $user = $this->createUser();
        $this->currentUser->login($user);
        $this->createSocialAccount((int) $user->getId());

        $html = (string) $controller->index()->getBody();

        // Without a client collection the raw provider key is shown and no connect widget renders.
        self::assertStringContainsString('github', $html);
        self::assertStringNotContainsString('session-auth', $html);
    }

    /**
     * Stubs for the asset stack so the AuthChoice widget's asset registration can run without
     * real path aliases or filesystem publishing in the test environment.
     *
     * @return array{class-string, object}
     */
    private function assetStubs(): array
    {
        return [
            AssetLoaderInterface::class => new class implements AssetLoaderInterface {
                public function getAssetUrl(AssetBundle $bundle, string $assetPath): string
                {
                    return '/assets/' . $assetPath;
                }

                public function loadBundle(string $name, array $config = []): AssetBundle
                {
                    if ($config !== []) {
                        return AssetUtil::createAsset($name, $config);
                    }

                    return new $name();
                }
            },
            AssetPublisherInterface::class => new class implements AssetPublisherInterface {
                public function publish(AssetBundle $bundle): array
                {
                    return ['', '/assets'];
                }

                public function getPublishedPath(string $sourcePath): ?string
                {
                    return $sourcePath;
                }

                public function getPublishedUrl(string $sourcePath): ?string
                {
                    return '/assets';
                }
            },
        ];
    }

    private function createController(array $overrides = []): SocialNetworkController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            ...$overrides,
        ])->get(SocialNetworkController::class);
    }

    private function createSocialAccount(int $userId, string $provider = 'github', string $username = 'octocat'): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setUserId($userId);
        $account->setProvider($provider);
        $account->setClientId('client123');
        $account->setUsername($username);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
