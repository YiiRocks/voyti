<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

final readonly class TwoFactorAuthenticationEnforceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CurrentUser $currentUser,
        private ModuleConfig $config,
        private ManagerInterface $authManager,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $handler->handle($request);
        }

        $user = $this->currentUser->getIdentity();
        $user = $user instanceof GuestIdentityInterface ? null : $user;
        if ($user === null || !$user instanceof User) {
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
                $response = $this->responseFactory->createResponse(Status::FOUND);
                return $response->withHeader(Header::LOCATION, $this->url->generate($this->config->accountSettingsRoute));
            }
        }

        return $handler->handle($request);
    }
}
