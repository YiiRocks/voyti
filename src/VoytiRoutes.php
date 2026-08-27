<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use Yiisoft\Csrf\CsrfTokenMiddleware;
use Yiisoft\Session\SessionMiddleware;

/**
 * Route-config helper, callable from `config/routes.php`. Exposes the web-middleware stack so
 * extension packages that contribute their own top-level route group (e.g. one owning a
 * login-confirmation route outside the `settings/` group) can wrap their routes in the same stack
 * the core group uses, instead of re-declaring — or drifting from — it.
 */
final class VoytiRoutes
{
    /**
     * The middleware every Voyti web route group runs: session, remember-me auto-login, CSRF, and
     * session-revocation enforcement, plus password-age enforcement when a max age is configured.
     *
     * @param array<string, mixed> $voytiParams the host's `yiirocks/voyti` params array
     *
     * @return list<class-string>
     */
    public static function webMiddleware(array $voytiParams): array
    {
        $middleware = [
            SessionMiddleware::class,
            RememberMeMiddleware::class,
            CsrfTokenMiddleware::class,
            SessionRevocationEnforceMiddleware::class,
        ];

        /** @infection-ignore-all The ?? 0 default applies only when a host omits maxPasswordAge; 0 and any negative fallback alike fall below the > 0 threshold, so the default value is unobservable. */
        if (($voytiParams['maxPasswordAge'] ?? 0) > 0) {
            $middleware[] = PasswordAgeEnforceMiddleware::class;
        }

        return $middleware;
    }
}
