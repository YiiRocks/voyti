<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;

trait RedirectTrait
{
    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $url);
    }
}
