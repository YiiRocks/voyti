<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Helper\FlashType;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;

/**
 * Adds redirect-response helpers to a controller, including a variant that queues a flash
 * message before redirecting. Requires the consumer to have `$responseFactory`, `$flash`, and
 * `$translator` properties.
 */
trait RedirectTrait
{
    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $url);
    }

    private function redirectWithFlash(string $url, string $messageKey): ResponseInterface
    {
        $this->flash->set(FlashType::SUCCESS, $this->translator->translate($messageKey, category: 'voyti'));

        return $this->redirect($url);
    }
}
