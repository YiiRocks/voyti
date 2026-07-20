<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Adds view-rendering helpers to a controller, injecting common view params (a `voyti`-category-bound
 * translator and resolved flash messages) and providing an error-message view shortcut. Requires the
 * consumer to have `$viewRenderer`, `$config`, `$translator`, `$url`, and `$flash` properties.
 * Templates never receive `ModuleConfig` or `UrlGeneratorInterface` directly - every other value a
 * template needs travels through an explicit `ViewData` object built by the controller.
 */
trait RenderTrait
{
    /**
     * @psalm-suppress UndefinedThisPropertyFetch
     */
    protected function homeUrl(): string
    {
        return $this->config->getHomeUrl($this->url);
    }

    protected function renderError(string $messageKey): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'data' => new MessageViewData(
                title: $this->translator->translate($messageKey, category: 'voyti'),
                homeUrl: $this->homeUrl(),
            ),
        ]);
    }

    /**
     * @psalm-suppress UndefinedThisPropertyFetch
     */
    protected function translator(): TranslatorInterface
    {
        return $this->translator->withDefaultCategory('voyti');
    }

    /**
     * @psalm-suppress UndefinedThisPropertyFetch
     */
    protected function viewPath(): string
    {
        return $this->config->viewPath;
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
            ->withViewPath($this->resolveViewPath($view))
            ->renderPartial($view, $this->withDefaultViewParams($params));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function renderView(string $view, array $params = []): ResponseInterface
    {
        return $this->viewRenderer
            ->withViewPath($this->resolveViewPath($view))
            ->render($view, $this->withDefaultViewParams($params));
    }

    /**
     * Uses the configured `viewPath` if it has an override for `$view`, otherwise falls back to
     * the module's bundled views so a host only needs to provide the templates it customizes.
     */
    private function resolveViewPath(string $view): string
    {
        $configuredPath = $this->viewPath();

        return is_file($configuredPath . '/' . $view . '.php') ? $configuredPath : ModuleConfig::DEFAULT_VIEW_PATH;
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
