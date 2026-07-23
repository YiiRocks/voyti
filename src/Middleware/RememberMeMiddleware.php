<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Cookies\CookieMiddleware;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

/**
 * Logs a guest back in from the remember-me cookie, then writes the cookie back onto the actual
 * response - either the immediate reissue after a session rotation, or the periodic sliding-expiration
 * refresh. {@see RememberMeCookieService::loginByCookie()} only authenticates the identity; it can't
 * write the cookie itself since it runs with no PSR-7 response available. This middleware is what does.
 */
final readonly class RememberMeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CurrentUser $currentUser,
        private RememberMeCookieService $rememberMeCookieService,
        private IdentityRepositoryInterface $identityRepository,
        private SessionInterface $session,
        private CookieMiddleware $cookieMiddleware,
    ) {}

    /**
     * Public so the {@see RequestHandlerInterface} adapter in {@see process()} can call it.
     */
    public function loginAndRefreshCookie(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $cookies = $request->getCookieParams();

        $rotated = $this->currentUser->isGuest()
            && $this->rememberMeCookieService->loginByCookie(
                $cookies,
                $this->currentUser,
                $this->identityRepository,
                $this->session,
                $request->getServerParams(),
            );

        $response = $handler->handle($request);

        if ($rotated) {
            $identity = $this->currentUser->getIdentity();
            if ($identity instanceof CookieLoginIdentityInterface) {
                return $this->rememberMeCookieService->addCookie($identity, $response, $this->session->getId() ?? '');
            }

            return $response;
        }

        return $this->rememberMeCookieService->refreshCookie($this->currentUser, $cookies, $response);
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->cookieMiddleware->process(
            $request,
            new class ($this, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private RememberMeMiddleware $middleware,
                    private RequestHandlerInterface $next,
                ) {}

                #[Override]
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->loginAndRefreshCookie($request, $this->next);
                }
            },
        );
    }
}
