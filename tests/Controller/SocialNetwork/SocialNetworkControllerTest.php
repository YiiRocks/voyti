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
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

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

        $this->currentUser->login($this->createUser());

        $html = (string) $controller->index()->getBody();

        self::assertStringContainsString('Networks', $html);
    }

    private function createController(): SocialNetworkController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
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
