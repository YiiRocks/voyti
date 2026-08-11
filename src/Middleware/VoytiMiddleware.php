<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Convenience wrapper that chains voyti's remember-me and enforcement middleware in the recommended order.
 *
 * Add this single middleware to your app's route group (or global pipeline, after session middleware)
 * instead of adding the sub-middlewares individually. Remember-me runs first (so a cookie-restored
 * user is present for the checks that follow), then every enforcement middleware collected via the
 * `voyti.enforce-middleware` DI tag - core contributes session-revocation and password-age, and
 * installed packages (e.g. `yiirocks/voyti-2fa`) contribute their own with no host wiring. Each
 * sub-middleware checks its own feature flag internally, so disabled features become no-ops.
 */
final readonly class VoytiMiddleware implements MiddlewareInterface
{
    /**
     * @param iterable<MiddlewareInterface> $enforcementMiddlewares
     */
    public function __construct(
        private MiddlewareInterface $rememberMe,
        private iterable $enforcementMiddlewares,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $middlewares = [$this->rememberMe];
        foreach ($this->enforcementMiddlewares as $enforcementMiddleware) {
            $middlewares[] = $enforcementMiddleware;
        }

        $handler = array_reduce(
            array_reverse($middlewares),
            static fn(
                RequestHandlerInterface $next,
                MiddlewareInterface $middleware,
            ): RequestHandlerInterface => new class ($middleware, $next) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $next,
                ) {}

                #[Override]
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            },
            $handler,
        );

        return $handler->handle($request);
    }
}
