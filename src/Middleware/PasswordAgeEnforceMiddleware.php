<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\ExpireService;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Redirects an authenticated user with an expired password to the account settings route.
 */
final readonly class PasswordAgeEnforceMiddleware implements MiddlewareInterface
{
    private const ACCOUNT_SETTINGS_ROUTE = 'voyti/user-account';

    /**
     * @var string[] Route names that must stay reachable even with an expired password, to avoid a redirect
     * loop on the target route itself and to always allow logging out.
     */
    private const EXEMPT_ROUTES = [self::ACCOUNT_SETTINGS_ROUTE, 'voyti/session-logout'];

    public function __construct(
        private CurrentUser $currentUser,
        private ExpireService $passwordExpireService,
        private CurrentRoute $currentRoute,
        private TranslatorInterface $translator,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        $user = $user instanceof GuestIdentityInterface ? null : $user;
        if ($user === null || !$user instanceof User) {
            return $handler->handle($request);
        }

        $routeName = $this->currentRoute->getName();
        if (in_array($routeName, self::EXEMPT_ROUTES, true)) {
            return $handler->handle($request);
        }

        if ($this->passwordExpireService->checkPasswordExpiration($user)) {
            $response = $this->responseFactory->createResponse(Status::FOUND);
            return $response->withHeader(Header::LOCATION, $this->url->generate(self::ACCOUNT_SETTINGS_ROUTE));
        }

        return $handler->handle($request);
    }
}
