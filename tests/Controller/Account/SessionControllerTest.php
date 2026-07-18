<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Account;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Account\SessionController;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SessionControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
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
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testIndexAuthenticatedUserNotFoundShowsError(): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('999999');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $controller = $this->createController();
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testIndexFlagsCurrentDevice(): void
    {
        $user = $this->createUser();
        $this->authenticateAs($user);

        $this->createSession($user, 'current-session', '203.0.113.1');
        $this->createSession($user, 'other-session', '203.0.113.2');

        $this->harness->getSession()->open();
        $this->harness->getSession()->setId('current-session');

        $controller = $this->createController();
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('account/sessions', $this->callback(
                static fn (array $params): bool => count($params['sessions']) === 2
                    && $params['currentSessionId'] === 'current-session'
                    && array_key_exists('timezone', $params),
            ))
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testIndexNotAuthenticatedRedirectsToLogin(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $controller = $this->createController();
        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testTerminateCurrentSessionLogsOutAndRedirectsToLogin(): void
    {
        $user = $this->createUser();
        $this->authenticateAs($user);
        $this->createSession($user, 'current-session', '203.0.113.1');

        $this->harness->getSession()->open();
        $this->harness->getSession()->setId('current-session');
        $this->currentUser->expects($this->once())->method('logout');

        $controller = $this->createController();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->terminate('current-session');

        $this->assertSame($response, $result);
        $event = $this->harness->getEventDispatcher()->getEvent(SessionEvent::class);
        $this->assertInstanceOf(SessionEvent::class, $event);
    }

    public function testTerminateNotAuthenticatedRedirectsToLogin(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $controller = $this->createController();
        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $controller->terminate('anything');

        $this->assertSame($response, $result);
    }

    public function testTerminateOtherSessionDeletesItAndRedirects(): void
    {
        $user = $this->createUser();
        $this->authenticateAs($user);
        $this->createSession($user, 'other-session', '203.0.113.1');

        $controller = $this->createController();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->terminate('other-session');

        $this->assertSame($response, $result);
        $this->assertNull(UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), 'other-session'));
    }

    public function testTerminateUnknownSessionShowsError(): void
    {
        $user = $this->createUser();
        $this->authenticateAs($user);

        $controller = $this->createController();
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->terminate('unknown-session');

        $this->assertSame($response, $result);
    }

    private function authenticateAs(User $user): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
    }

    private function createController(): SessionController
    {
        return $this->harness->createAccountSessionController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
        );
    }

    private function createSession(User $user, string $sessionId, string $ip): UserSessions
    {
        $session = new UserSessions();
        $session->setUserId($user->getIdOrZero());
        $session->setSessionId($sessionId);
        $session->setIp($ip);
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        return $session;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('sessionuser');
        $user->setEmail('sessionuser@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
