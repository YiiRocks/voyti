<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\SocialNetwork;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\SocialNetwork\SocialNetworkController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SocialNetworkControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = new PasswordHasher();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testDeleteWhenGuestRedirectsToLogin(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $controller->delete($request, 1);

        $this->assertSame($response, $result);
    }

    public function testDeleteWithFoundAccountDeletesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $account = $this->createSocialAccount((int) $user->getId());
        $accountId = $account->getId();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->delete($request, $accountId);

        $this->assertSame($response, $result);
        $this->assertSame([], UserSocialAccount::findByUserId((int) $user->getId()));
    }

    public function testDeleteWithNoAccountShowsNotFound(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
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

        $result = $controller->delete($request, 999);

        $this->assertSame($response, $result);
    }

    public function testIndexShowsConnectedAccounts(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

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

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testIndexWhenGuestRedirectsToLogin(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    private function createController(): SocialNetworkController
    {
        return $this->harness->createSocialNetworkController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
        );
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

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash($this->passwordHasher->hash('secret'));
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
