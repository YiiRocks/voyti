<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

trait RedirectResponseMockTrait
{
    private function mockRedirectResponse(
        MockObject&ResponseFactoryInterface $responseFactory,
        ?string $location = null,
    ): MockObject&ResponseInterface {
        $response = $this->createMock(ResponseInterface::class);
        $responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);

        if ($location !== null) {
            $response->expects($this->once())
                ->method('withHeader')
                ->with('Location', $location)
                ->willReturnSelf();
        } else {
            $response->expects($this->once())
                ->method('withHeader')
                ->willReturnSelf();
        }

        return $response;
    }
}
