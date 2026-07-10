<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;

trait RedirectTrait
{
    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $url);
    }

    private function redirectWithFlash(string $url, string $messageKey): ResponseInterface
    {
        $this->flash->set('success', $this->translator->translate($messageKey, category: 'voyti'));

        return $this->redirect($url);
    }
}
