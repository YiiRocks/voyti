<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\Auth\Method\HttpBearer;
use Yiisoft\Http\Status;
use Yiisoft\User\CurrentUser;

/**
 * Bearer-token authentication for the `enableRestApi` route group: resolves the token to a
 * `User` via `yiisoft/auth`'s `HttpBearer`/`IdentityWithTokenRepositoryInterface` and overrides
 * `CurrentUser` for the request, or challenges with 401 if the token is missing/invalid. No
 * session is involved, unlike the cookie-based web auth flow.
 */
final readonly class ApiTokenAuthenticationMiddleware implements MiddlewareInterface
{
    private HttpBearer $httpBearer;

    public function __construct(
        IdentityWithTokenRepositoryInterface $identityRepository,
        private ResponseFactoryInterface $responseFactory,
        private CurrentUser $currentUser,
    ) {
        $this->httpBearer = new HttpBearer($identityRepository);
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $this->httpBearer->authenticate($request);

        if ($identity === null) {
            return $this->httpBearer->challenge($this->responseFactory->createResponse(Status::UNAUTHORIZED));
        }

        $this->currentUser->overrideIdentity($identity);

        return $handler->handle($request);
    }
}
