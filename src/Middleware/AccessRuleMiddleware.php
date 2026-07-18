<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Guards admin routes: redirects guests to `voyti/session-login` and returns 403 for
 * authenticated users lacking the configured administrator permission.
 */
final readonly class AccessRuleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CurrentUser $currentUser,
        private AuthHelper $authHelper,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();

        if ($user instanceof GuestIdentityInterface) {
            $response = $this->responseFactory->createResponse(Status::FOUND);
            return $response->withHeader(Header::LOCATION, $this->url->generate('voyti/session-login'));
        }

        $userId = $user->getId() ?? 0;
        if (!$this->authHelper->isAdmin($userId)) {
            $response = $this->responseFactory->createResponse(Status::FORBIDDEN);
            return $response;
        }

        return $handler->handle($request);
    }
}
