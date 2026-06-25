<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Voyti;

trait RenderTrait
{
    protected function viewPath(): string
    {
        return Voyti::VIEWS_PATH;
    }

    protected function renderError(string $messageKey): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'title' => $this->translator->translate($messageKey, category: Voyti::TRANSLATION_CATEGORY),
            'translator' => $this->translator,
        ]);
    }

    protected function renderSuccess(string $messageKey): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'title' => $this->translator->translate($messageKey, category: Voyti::TRANSLATION_CATEGORY),
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
        $params['csrf'] ??= '';

        return $this->viewRenderer
            ->withViewPath($this->viewPath())
            ->render($view, $params);
    }
}
