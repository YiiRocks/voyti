<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\tests\TestCase;

final class RenderTraitTest extends TestCase
{
    public function testRenderErrorIsProtectedNotPrivate(): void
    {
        $method = new ReflectionMethod(RenderErrorSuccessHost::class, 'renderError');

        self::assertTrue($method->isProtected());
    }

    public function testRenderErrorUsesTranslatedTitleAndSharedTranslator(): void
    {
        $host = new RenderErrorSuccessHost();
        $translator = new FakeTranslator();
        $host->translator = $translator;
        $host->url = new IdentityMarker('url-marker');

        $host->callRenderError('voyti.some.message');

        $renderer = $host->viewRenderer;
        self::assertSame('shared/message', $renderer->capturedView);
        self::assertSame(
            [
                'title' => 'translated:voyti.some.message',
                'translator' => $translator,
                'url' => $host->url,
            ],
            $renderer->capturedParams,
        );
    }

    public function testRenderSuccessIsProtectedNotPrivate(): void
    {
        $method = new ReflectionMethod(RenderErrorSuccessHost::class, 'renderSuccess');

        self::assertTrue($method->isProtected());
    }

    public function testRenderSuccessUsesTranslatedTitleAndSharedTranslator(): void
    {
        $host = new RenderErrorSuccessHost();
        $translator = new FakeTranslator();
        $host->translator = $translator;
        $host->url = new IdentityMarker('url-marker');

        $host->callRenderSuccess('voyti.other.message');

        $renderer = $host->viewRenderer;
        self::assertSame('shared/message', $renderer->capturedView);
        self::assertSame(
            [
                'title' => 'translated:voyti.other.message',
                'translator' => $translator,
                'url' => $host->url,
            ],
            $renderer->capturedParams,
        );
    }

    public function testRenderViewFillsInDefaultTranslatorAndUrlWhenAbsent(): void
    {
        $host = new RenderErrorSuccessHost();
        $host->translator = new IdentityMarker('default-translator');
        $host->url = new IdentityMarker('default-url');

        $host->callRenderView('some/view', ['title' => 'hello']);

        self::assertSame(
            [
                'title' => 'hello',
                'translator' => $host->translator,
                'url' => $host->url,
            ],
            $host->viewRenderer->capturedParams,
        );
    }

    public function testRenderViewKeepsPreSetTranslatorInsteadOfOverwriting(): void
    {
        $host = new RenderErrorSuccessHost();
        $host->translator = new IdentityMarker('default-translator');
        $host->url = new IdentityMarker('default-url');

        $preSetTranslator = new IdentityMarker('pre-set-translator');
        $host->callRenderView('some/view', ['title' => 'hello', 'translator' => $preSetTranslator]);

        $params = $host->viewRenderer->capturedParams;
        self::assertSame($preSetTranslator, $params['translator']);
        self::assertSame($host->url, $params['url']);
    }

    public function testRenderViewKeepsPreSetUrlInsteadOfOverwriting(): void
    {
        $host = new RenderErrorSuccessHost();
        $host->translator = new IdentityMarker('default-translator');
        $host->url = new IdentityMarker('default-url');

        $preSetUrl = new IdentityMarker('pre-set-url');
        $host->callRenderView('some/view', ['title' => 'hello', 'url' => $preSetUrl]);

        $params = $host->viewRenderer->capturedParams;
        self::assertSame($preSetUrl, $params['url']);
        self::assertSame($host->translator, $params['translator']);
    }

    public function testViewPathIsProtectedNotPrivate(): void
    {
        $method = new ReflectionMethod(RenderErrorSuccessHost::class, 'viewPath');

        self::assertTrue($method->isProtected());
    }

    public function testViewPathPointsToBootstrap5Views(): void
    {
        $host = new RenderErrorSuccessHost();
        $host->translator = new IdentityMarker('translator');
        $host->url = new IdentityMarker('url');

        $host->callRenderView('some/view', []);

        $traitFile = (new ReflectionClass(RenderTrait::class))->getFileName();
        self::assertIsString($traitFile);
        $expectedViewPath = dirname($traitFile, 2) . '/resources/views/bootstrap5';

        self::assertSame($expectedViewPath, $host->viewRenderer->capturedViewPath);
    }
}

final class FakeTranslator
{
    public function translate(string $id, array $parameters = [], ?string $category = null, ?string $locale = null): string
    {
        return 'translated:' . $id;
    }
}

final class FakeViewRenderer
{
    public ?array $capturedParams = null;
    public ?string $capturedView = null;
    public ?string $capturedViewPath = null;

    public function render(string $view, array $params): ResponseInterface
    {
        $this->capturedView = $view;
        $this->capturedParams = $params;

        return new FakeResponse();
    }

    public function withViewPath(string $viewPath): self
    {
        $this->capturedViewPath = $viewPath;

        return $this;
    }
}

final class FakeResponse implements ResponseInterface
{
    /** @var array<string, string[]> */
    private array $headers = [];
    private string $reasonPhrase = '';
    private int $statusCode = 200;

    public function getBody(): \Psr\Http\Message\StreamInterface
    {
        throw new \RuntimeException('not implemented');
    }

    public function getHeader(string $name): array
    {
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->headers[$name][] = $value;

        return $clone;
    }

    public function withBody(\Psr\Http\Message\StreamInterface $body): static
    {
        return clone $this;
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = (array) $value;

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        unset($clone->headers[$name]);

        return $clone;
    }

    public function withProtocolVersion(string $version): static
    {
        return clone $this;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;

        return $clone;
    }
}

final class IdentityMarker
{
    public function __construct(public readonly string $label)
    {
    }
}

final class RenderErrorSuccessHost
{
    use RenderTrait;

    public object $translator;
    public object $url;
    public FakeViewRenderer $viewRenderer;

    public function __construct()
    {
        $this->viewRenderer = new FakeViewRenderer();
    }

    public function callRenderError(string $messageKey): ResponseInterface
    {
        return $this->renderError($messageKey);
    }

    public function callRenderSuccess(string $messageKey): ResponseInterface
    {
        return $this->renderSuccess($messageKey);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function callRenderView(string $view, array $params = []): ResponseInterface
    {
        return $this->renderView($view, $params);
    }
}
