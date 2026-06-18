<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Auth\IdentityServiceInterface;
use Yiisoft\Rbac\ManagerInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;

final class AccessRuleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly IdentityServiceInterface $identityService,
        private readonly ModuleConfig $config,
        private readonly AuthHelper $authHelper,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->identityService->getIdentity();

        if ($user === null) {
            $response = $this->responseFactory->createResponse(302);
            return $response->withHeader('Location', '/user/login');
        }

        $userId = $user->getId() ?? 0;
        if (!$this->authHelper->isAdmin($userId)) {
            $response = $this->responseFactory->createResponse(403);
            return $response;
        }

        return $handler->handle($request);
    }
}
