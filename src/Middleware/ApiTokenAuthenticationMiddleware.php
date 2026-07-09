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
use Yiisoft\User\CurrentUser;

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
            return $this->httpBearer->challenge($this->responseFactory->createResponse(401));
        }

        $this->currentUser->overrideIdentity($identity);

        return $handler->handle($request);
    }
}
