<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Settings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Settings\SettingsController;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class SettingsControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
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
        $this->flash = $this->createMock(FlashInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testIndexShowsView(): void
    {
        $user = $this->createUser();
        $this->currentUser->method('getIdentity')->willReturn($user);
        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    private function createController(): SettingsController
    {
        return $this->harness->createSettingsController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            flash: $this->flash,
            currentUser: $this->currentUser,
        );
    }
}
