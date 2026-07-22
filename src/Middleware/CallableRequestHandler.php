<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Closure;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapts a closure to {@see RequestHandlerInterface}, needed to pass inline logic as the "next
 * handler" to third-party middleware (e.g. {@see \Yiisoft\Cookies\CookieMiddleware}) that require
 * the PSR-15 interface rather than a callable.
 */
final readonly class CallableRequestHandler implements RequestHandlerInterface
{
    /**
     * @param Closure(ServerRequestInterface): ResponseInterface $handler
     */
    public function __construct(
        private Closure $handler,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->handler)($request);
    }
}
