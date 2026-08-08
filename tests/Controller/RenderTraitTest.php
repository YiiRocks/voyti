<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class RenderTraitTest extends TestCase
{
    use TestContainerTrait;

    public function testAddsCsrfInjectionForTheRenderCall(): void
    {
        // The template only renders the CSRF token if RenderTrait added the CsrfViewInjection.
        $customViewPath = $this->makeViewPath('<?= $csrf->getToken() ?>');

        try {
            $config = VoytiConfigFactory::create(viewPath: $customViewPath);
            $html = (string) $this->makeFixture($config)->render('shared/message')->getBody();

            self::assertStringContainsString('test-csrf-token', $html);
        } finally {
            $this->removeViewPath($customViewPath);
        }
    }

    public function testFallsBackToThemeViewPathWhenTemplateIsMissingFromConfiguredPath(): void
    {
        // The configured path has no shared/message.php, so rendering falls back to the bundled theme.
        $customViewPath = sys_get_temp_dir() . '/voyti-render-trait-test-' . uniqid();
        mkdir($customViewPath);

        try {
            $config = VoytiConfigFactory::create(viewPath: $customViewPath);
            $html = (string) $this->makeFixture($config)
                ->render('shared/message', ['data' => new MessageViewData(title: 'THEME_MESSAGE', homeUrl: '/')])
                ->getBody();

            self::assertStringContainsString('THEME_MESSAGE', $html);
            self::assertStringNotContainsString('CUSTOM_TEMPLATE', $html);
        } finally {
            rmdir($customViewPath);
        }
    }

    private function makeFixture(VoytiConfig $config): object
    {
        $viewRenderer = $this->getTestContainer()->get(WebViewRenderer::class);

        return new class ($viewRenderer, $config, $this->createTranslator(), new FakeUrlGenerator(), $this->createMock(FlashInterface::class)) {
            use RenderTrait;

            public function __construct(
                private WebViewRenderer $viewRenderer,
                private VoytiConfig $config,
                private TranslatorInterface $translator,
                private UrlGeneratorInterface $url,
                private FlashInterface $flash,
            ) {}

            public function render(string $view, array $params = []): ResponseInterface
            {
                return $this->renderView($view, $params);
            }
        };
    }

    private function makeViewPath(string $messageTemplateBody): string
    {
        $path = sys_get_temp_dir() . '/voyti-render-trait-test-' . uniqid();
        mkdir($path);
        mkdir($path . '/shared');
        file_put_contents($path . '/shared/message.php', $messageTemplateBody);

        return $path;
    }

    private function removeViewPath(string $path): void
    {
        unlink($path . '/shared/message.php');
        rmdir($path . '/shared');
        rmdir($path);
    }
}
