<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;

trait RenderTrait
{

    protected function renderError(string $messageKey): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'title' => $this->translator->translate($messageKey, category: 'voyti'),
            'translator' => $this->translator,
        ]);
    }

    protected function renderSuccess(string $messageKey): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'title' => $this->translator->translate($messageKey, category: 'voyti'),
            'translator' => $this->translator,
        ]);
    }
    /**
     * @param array<string, mixed> $params
     */
    private function renderView(string $view, array $params = []): ResponseInterface
    {
        $params['translator'] ??= $this->translator;
        $params['url'] ??= $this->url;

        return $this->viewRenderer
            ->withViewPath('@voytiViews')
            ->render($view, $params);
    }
}
