<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;

/**
 * Adds view-rendering helpers to a controller, injecting common view params (a `voyti`-category-bound
 * translator and resolved flash messages) and providing an error-message view shortcut. Requires the
 * consumer to have `$viewRenderer`, `$config`, `$translator`, `$url`, and `$flash` properties.
 * Templates never receive `VoytiConfig` or `UrlGeneratorInterface` directly - every other value a
 * template needs travels through an explicit `ViewData` object built by the controller.
 */
trait RenderTrait
{
    /**
     * @psalm-suppress UndefinedThisPropertyFetch
     */
    private function homeUrl(): string
    {
        return $this->config->getHomeUrl($this->url);
    }

    private function renderError(string $messageKey): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'data' => new MessageViewData(
                title: $this->translator->translate($messageKey, category: 'voyti'),
                homeUrl: $this->homeUrl(),
            ),
        ]);
    }

    /**
     * Renders a view without the host application's layout - used for AJAX fragments that
     * get injected into an existing page rather than replacing it.
     *
     * @param array<string, mixed> $params
     */
    private function renderFragment(string $view, array $params = []): ResponseInterface
    {
        return $this->viewRenderer
            ->withAddedInjections(CsrfViewInjection::class)
            ->withViewPath($this->resolveViewPath($view))
            ->renderPartial($view, $this->withDefaultViewParams($params));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function renderView(string $view, array $params = []): ResponseInterface
    {
        return $this->viewRenderer
            ->withAddedInjections(CsrfViewInjection::class)
            ->withViewPath($this->resolveViewPath($view))
            ->render($view, $this->withDefaultViewParams($params));
    }

    /**
     * Uses the configured `viewPath` if it has an override for `$view`, otherwise falls back to
     * the module's bundled views so a host only needs to provide the templates it customizes.
     */
    private function resolveViewPath(string $view): string
    {
        if ($this->config->viewPath !== null && is_file($this->config->viewPath . '/' . $view . '.php')) {
            return $this->config->viewPath;
        }

        return dirname(__DIR__, 2) . '/resources/views/' . $this->config->webTheme->value;
    }

    /**
     * @psalm-suppress UndefinedThisPropertyFetch
     */
    private function translator(): TranslatorInterface
    {
        return $this->translator->withDefaultCategory('voyti');
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function withDefaultViewParams(array $params): array
    {
        if (!isset($params['translator'])) {
            $params['translator'] = $this->translator();
        }
        if (!isset($params['flash'])) {
            $params['flash'] = FlashViewData::fromFlash($this->flash);
        }

        return $params;
    }
}
