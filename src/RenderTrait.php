<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\View\ViewInterface;

trait RenderTrait
{
    private function renderView(string $view, array $params = []): ResponseInterface
    {
        $params['translator'] ??= $this->translator;
        $params['url'] ??= $this->url;

        $content = $this->view->render(
            $this->aliases->get('@voytiViews') . '/' . $view,
            $params,
        );
        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write($content);
        return $response;
    }
}
