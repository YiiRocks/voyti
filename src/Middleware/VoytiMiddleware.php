<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Convenience wrapper that chains the three enforcement middleware in the recommended order:
 *
 * 1. {@see SessionRevocationEnforceMiddleware} — logs out when the session was terminated elsewhere
 * 2. {@see PasswordAgeEnforceMiddleware} — forces password change when maxPasswordAge is exceeded
 * 3. {@see TwoFactorAuthenticationEnforceMiddleware} — forces 2FA setup when required permissions are assigned
 *
 * Add this single middleware to your app's route group (or global pipeline, after session middleware)
 * instead of adding the three sub-middlewares individually. Each sub-middleware still checks its own
 * feature flag internally, so features that are disabled in {@see \YiiRocks\Voyti\ModuleConfig}
 * become no-ops without any extra configuration.
 */
final readonly class VoytiMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MiddlewareInterface $passwordAge,
        private MiddlewareInterface $sessionRevocation,
        private MiddlewareInterface $twoFactorAuth,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $middlewares = [
            $this->sessionRevocation,
            $this->passwordAge,
            $this->twoFactorAuth,
        ];

        $handler = array_reduce(
            array_reverse($middlewares),
            static fn (RequestHandlerInterface $next, MiddlewareInterface $middleware): RequestHandlerInterface => new class($middleware, $next) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $next,
                ) {
                }

                #[\Override]
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
