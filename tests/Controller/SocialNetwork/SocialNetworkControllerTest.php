<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\SocialNetwork;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\SocialNetwork\SocialNetworkController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class SocialNetworkControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testDeleteWithFoundAccountDeletesAndRedirects(): void
    {
        $controller = $this->createController();

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'));
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $account = $this->createSocialAccount((int) $user->getId());
        $accountId = $account->getId();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->delete($accountId);

        $this->assertSame($response, $result);
        $this->assertSame([], UserSocialAccount::findByUserId((int) $user->getId()));
    }

    public function testDeleteWithNoAccountShowsNotFound(): void
    {
        $controller = $this->createController();

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'));
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->delete(999);

        $this->assertSame($response, $result);
    }

    public function testIndexShowsConnectedAccounts(): void
    {
        $controller = $this->createController();

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('social-network/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    private function createController(): SocialNetworkController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            ResponseFactoryInterface::class => $this->responseFactory,
            WebViewRenderer::class => $this->viewRenderer,
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
