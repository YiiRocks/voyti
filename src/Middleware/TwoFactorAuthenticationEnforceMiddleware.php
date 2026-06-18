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
use YiiRocks\Voyti\ModuleConfig;

final class TwoFactorAuthenticationEnforceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly IdentityServiceInterface $identityService,
        private readonly ModuleConfig $config,
        private readonly ManagerInterface $authManager,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $handler->handle($request);
        }

        $user = $this->identityService->getIdentity();
        if ($user === null) {
            return $handler->handle($request);
        }

        $permissions = $this->config->twoFactorAuthenticationForcedPermissions;
        if (empty($permissions)) {
            return $handler->handle($request);
        }

        $userId = $user->getId() ?? 0;
        $userPermissions = $this->authManager->getPermissionsByUserId($userId);
        $userPermissionNames = array_keys($userPermissions);

        if (!empty(array_intersect($permissions, $userPermissionNames))) {
            if (!$user->isAuthTfEnabled()) {
                $response = $this->responseFactory->createResponse(302);
                return $response->withHeader('Location', '/voyti/settings/account');
            }
        }

        return $handler->handle($request);
    }
}
