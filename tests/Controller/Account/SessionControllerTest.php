<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Account;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class SessionControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

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
        $this->config = ModuleConfigFactory::create();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testIndexFlagsCurrentDevice(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
        $this->authenticateAs($user);

        $this->createUserSession($user->getIdOrZero(), 'current-session', '203.0.113.1');
        $this->createUserSession($user->getIdOrZero(), 'other-session', '203.0.113.2');

        $this->harness->getSession()->open();
        $this->harness->getSession()->setId('current-session');

        $controller = $this->createController();
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('account/sessions', $this->callback(
                static function (array $params): bool {
                    $sessions = $params['data']->sessions;
                    $currentCount = count(array_filter($sessions, static fn($row): bool => $row->isCurrentSession));
                    return count($sessions) === 2 && $currentCount === 1;
                },
            ))
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testTerminateCurrentSessionLogsOutAndRedirectsToLogin(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
        $this->authenticateAs($user);
        $this->createUserSession($user->getIdOrZero(), 'current-session', '203.0.113.1');

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

    public function testTerminateOtherSessionRevokesItAndRedirects(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
        $this->authenticateAs($user);
        $this->createUserSession($user->getIdOrZero(), 'other-session', '203.0.113.1');

        $controller = $this->createController();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->terminate('other-session');

        $this->assertSame($response, $result);
        $revoked = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), 'other-session');
        $this->assertNotNull($revoked);
        $this->assertTrue($revoked->isRevoked());
    }

    public function testTerminateUnknownSessionShowsError(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
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
        $this->currentUser->method('getIdentity')->willReturn($user);
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

}
