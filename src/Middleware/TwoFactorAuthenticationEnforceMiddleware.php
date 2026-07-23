<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Redirects an authenticated user to the two-factor settings route, with an explanatory flash
 * message, if two-factor authentication is required for one of their permissions but not yet
 * enabled on their account.
 */
final readonly class TwoFactorAuthenticationEnforceMiddleware implements MiddlewareInterface
{
    private const LOGOUT_ROUTE = 'voyti/session-logout';
    private const TWO_FACTOR_ROUTE = 'voyti/user-two-factor';

    public function __construct(
        private CurrentUser $currentUser,
        private ModuleConfig $config,
        private ManagerInterface $authManager,
        private CurrentRoute $currentRoute,
        private ResponseFactoryInterface $responseFactory,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $url,
        private ?FlashInterface $flash = null,
    ) {}

    #[Override]
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

        $routeName = $this->currentRoute->getName();
        if (
            $routeName !== null
            && ($routeName === self::LOGOUT_ROUTE || str_starts_with($routeName, self::TWO_FACTOR_ROUTE))
        ) {
            return $handler->handle($request);
        }

        $permissions = $this->config->twoFactorAuthenticationForcedPermissions;
        if (empty($permissions)) {
            return $handler->handle($request);
        }

        $userId = $user->getId() ?? 0;
        $userPermissions = $this->authManager->getPermissionsByUserId($userId);
        $userPermissionNames = array_keys($userPermissions);

        if (!empty(array_intersect($permissions, $userPermissionNames)) && !$user->isAuthTfEnabled()) {
            $this->flash?->set(
                FlashType::WARNING,
                $this->translator->translate('voyti.security.two_factor_required', category: 'voyti'),
            );

            $response = $this->responseFactory->createResponse(Status::FOUND);
            return $response->withHeader(Header::LOCATION, $this->url->generate(self::TWO_FACTOR_ROUTE));
        }

        return $handler->handle($request);
    }
}
