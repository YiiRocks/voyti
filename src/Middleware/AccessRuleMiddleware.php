<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

final readonly class AccessRuleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CurrentUser $currentUser,
        private ModuleConfig $config,
        private AuthHelper $authHelper,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();

        if ($user instanceof GuestIdentityInterface) {
            $response = $this->responseFactory->createResponse(302);
            return $response->withHeader('Location', $this->url->generate($this->config->loginRoute));
        }

        $userId = $user->getId() ?? 0;
        if (!$this->authHelper->isAdmin($userId)) {
            $response = $this->responseFactory->createResponse(403);
            return $response;
        }

        return $handler->handle($request);
    }
}
