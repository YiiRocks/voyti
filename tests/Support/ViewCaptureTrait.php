<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Psr\Http\Message\ResponseInterface;
use stdClass;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

trait ViewCaptureTrait
{
    private function captureRenderedView(WebViewRenderer $viewRenderer): array
    {
        $state = new stdClass();
        $state->params = null;
        $response = $this->createMock(ResponseInterface::class);
        $viewRenderer->method('withViewPath')->willReturnSelf();
        $viewRenderer->method('render')->willReturnCallback(
            static function (string $view, array $params) use ($state, $response): ResponseInterface {
                $state->params = $params;
                return $response;
            },
        );

        return [$state, $response];
    }
}
