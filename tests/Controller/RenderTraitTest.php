<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class RenderTraitTest extends TestCase
{
    public function testAddsCsrfInjectionScopedToTheRenderCallOnly(): void
    {
        $config = VoytiConfigFactory::create();
        $viewRenderer = $this->createMock(WebViewRenderer::class);
        $response = $this->createMock(ResponseInterface::class);

        $viewRenderer->expects(self::once())
            ->method('withAddedInjections')
            ->with(CsrfViewInjection::class)
            ->willReturnSelf();
        $viewRenderer->method('withViewPath')->willReturnSelf();
        $viewRenderer->method('render')->willReturn($response);

        $fixture = new class ($viewRenderer, $config, $this->createTranslator(), new FakeUrlGenerator(), $this->createMock(FlashInterface::class)) {
            use RenderTrait;

            public function __construct(
                private WebViewRenderer $viewRenderer,
                private VoytiConfig $config,
                private TranslatorInterface $translator,
                private UrlGeneratorInterface $url,
                private FlashInterface $flash,
            ) {}

            public function render(string $view): ResponseInterface
            {
                return $this->renderView($view);
            }
        };

        $fixture->render('shared/message');
    }

    public function testFallsBackToDefaultViewPathWhenTemplateIsMissingFromConfiguredPath(): void
    {
        $customViewPath = sys_get_temp_dir() . '/voyti-render-trait-test-' . uniqid();
        mkdir($customViewPath);

        try {
            $capturedPath = $this->renderWithConfiguredPath($customViewPath, 'shared/message');

            self::assertSame(VoytiConfig::DEFAULT_VIEW_PATH, $capturedPath);
        } finally {
            rmdir($customViewPath);
        }
    }

    public function testUsesConfiguredViewPathWhenTemplateExistsThere(): void
    {
        $customViewPath = sys_get_temp_dir() . '/voyti-render-trait-test-' . uniqid();
        mkdir($customViewPath);
        mkdir($customViewPath . '/shared');
        file_put_contents($customViewPath . '/shared/message.php', '<?php');

        try {
            $capturedPath = $this->renderWithConfiguredPath($customViewPath, 'shared/message');

            self::assertSame($customViewPath, $capturedPath);
        } finally {
            unlink($customViewPath . '/shared/message.php');
            rmdir($customViewPath . '/shared');
            rmdir($customViewPath);
        }
    }

    private function renderWithConfiguredPath(string $viewPath, string $view): ?string
    {
        $config = VoytiConfigFactory::create(viewPath: $viewPath);
        $viewRenderer = $this->createMock(WebViewRenderer::class);
        $response = $this->createMock(ResponseInterface::class);

        $capturedPath = null;
        $viewRenderer->method('withAddedInjections')->willReturnSelf();
        $viewRenderer->method('withViewPath')->willReturnCallback(
            function (string $path) use ($viewRenderer, &$capturedPath): WebViewRenderer {
                $capturedPath = $path;
                return $viewRenderer;
            },
        );
        $viewRenderer->method('render')->willReturn($response);

        $fixture = new class ($viewRenderer, $config, $this->createTranslator(), new FakeUrlGenerator(), $this->createMock(FlashInterface::class)) {
            use RenderTrait;

            public function __construct(
                private WebViewRenderer $viewRenderer,
                private VoytiConfig $config,
                private TranslatorInterface $translator,
                private UrlGeneratorInterface $url,
                private FlashInterface $flash,
            ) {}

            public function render(string $view): ResponseInterface
            {
                return $this->renderView($view);
            }
        };

        $fixture->render($view);

        return $capturedPath;
    }
}
