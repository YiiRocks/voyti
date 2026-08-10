<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Helper\FlashType;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;

/**
 * Adds redirect-response helpers to a controller, including a variant that queues a toast
 * message before redirecting. Requires the consumer to have `$responseFactory`, `$toast`, and
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
        $message = $this->translator->translate($messageKey, category: 'voyti');
        $this->toast->add(FlashType::SUCCESS, $message);

        return $this->redirect($url);
    }
}
