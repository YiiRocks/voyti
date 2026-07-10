<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;

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
            'title' => $this->translator->translate($messageKey, category: 'voyti'),
        ]);
    }

    protected function viewPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/views/bootstrap5';
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
            ->withViewPath($this->viewPath())
            ->renderPartial($view, $this->withDefaultViewParams($params));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function renderView(string $view, array $params = []): ResponseInterface
    {
        return $this->viewRenderer
            ->withViewPath($this->viewPath())
            ->render($view, $this->withDefaultViewParams($params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function withDefaultViewParams(array $params): array
    {
        if (!isset($params['translator'])) {
            $params['translator'] = $this->translator;
        }
        if (!isset($params['url'])) {
            $params['url'] = $this->url;
        }
        if (!isset($params['homeUrl'])) {
            $params['homeUrl'] = $this->homeUrl();
        }

        return $params;
    }
}
