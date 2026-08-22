<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Terminating a session only flags its {@see UserSessions} row as revoked — the browser's PHP
 * session stays valid until it expires naturally. This middleware closes that gap by
 * force-logging-out once the row is gone or revoked, also expiring the remember-me cookie so a
 * dead cookie doesn't linger in the browser silently failing on every future visit (most visible
 * with cross-domain SSO logout, where another domain's session gets revoked out from under it).
 */
final readonly class SessionRevocationEnforceMiddleware implements MiddlewareInterface
{
    /**
     * @var string[] Route names that must stay reachable even for a revoked session, to always allow
     * logging out and to avoid a redirect loop on the login route itself.
     */
    private const array EXEMPT_ROUTES = ['voyti/session-login', 'voyti/session-logout'];

    public function __construct(
        private CurrentUser $currentUser,
        private CurrentRoute $currentRoute,
        private RememberMeCookieService $rememberMeCookieService,
        private ResponseFactoryInterface $responseFactory,
        private SessionInterface $session,
        private UrlGeneratorInterface $url,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        $user = $user instanceof GuestIdentityInterface ? null : $user;
        if ($user === null || !$user instanceof User) {
            return $handler->handle($request);
        }

        if (in_array($this->currentRoute->getName(), self::EXEMPT_ROUTES, true)) {
            return $handler->handle($request);
        }

        $sessionId = $this->session->getId();
        if ($sessionId === null || $sessionId === '') {
            return $handler->handle($request);
        }

        $userSession = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), $sessionId);

        if ($userSession === null || $userSession->isRevoked()) {
            $this->currentUser->logout();
            $response = $this->responseFactory->createResponse(Status::FOUND)
                ->withHeader(Header::LOCATION, $this->url->generate('voyti/session-login'));
            return $this->rememberMeCookieService->expireCookie($response);
        }

        $userSession->setUpdatedAt(time());
        $userSession->save();

        return $handler->handle($request);
    }
}
