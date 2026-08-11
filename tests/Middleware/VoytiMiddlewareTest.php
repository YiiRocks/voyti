<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Middleware\VoytiMiddleware;

#[AllowMockObjectsWithoutExpectations]
final class VoytiMiddlewareTest extends TestCase
{
    public function testProcessChainsRememberMeThenEnforcementMiddlewares(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = new VoytiMiddleware(
            $this->createPassThroughMiddleware(),
            [$this->createPassThroughMiddleware(), $this->createPassThroughMiddleware()],
        );

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testProcessExecutesRememberMeFirstThenEnforcementInOrder(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $log = [];
        $middleware = new VoytiMiddleware(
            $this->createRecordingMiddleware('rememberMe', $log),
            [
                $this->createRecordingMiddleware('sessionRevocation', $log),
                $this->createRecordingMiddleware('passwordAge', $log),
            ],
        );

        $middleware->process($request, $handler);

        // Remember-me runs first, then each enforcement middleware in tag order.
        self::assertSame(['rememberMe', 'sessionRevocation', 'passwordAge'], $log);
    }

    public function testProcessShortCircuitsWhenAnEnforcementMiddlewareReturnsEarly(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $redirect = $this->createMock(ResponseInterface::class);

        // The final handler must never run: the first enforcement middleware returns its own response.
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $log = [];
        $middleware = new VoytiMiddleware(
            $this->createRecordingMiddleware('rememberMe', $log),
            [
                $this->createRedirectMiddleware($redirect),
                $this->createRecordingMiddleware('neverRun', $log),
            ],
        );

        self::assertSame($redirect, $middleware->process($request, $handler));
        self::assertSame(['rememberMe'], $log);
    }

    private function createPassThroughMiddleware(): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(
            static fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface => $handler->handle($request),
        );

        return $middleware;
    }

    private function createRecordingMiddleware(string $label, array &$log): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($label, &$log): ResponseInterface {
                $log[] = $label;

                return $handler->handle($request);
            },
        );

        return $middleware;
    }

    private function createRedirectMiddleware(ResponseInterface $response): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturn($response);

        return $middleware;
    }
}
