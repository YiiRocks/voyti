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
use YiiRocks\Voyti\Service\Password\ExpireService;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

final readonly class PasswordAgeEnforceMiddleware implements MiddlewareInterface
{
    /**
     * @var string[] Route names that must stay reachable even with an expired password, to avoid a redirect
     * loop on the target route itself and to always allow logging out.
     */
    private const EXEMPT_ROUTES = ['voyti/logout'];

    public function __construct(
        private CurrentUser $currentUser,
        private ModuleConfig $config,
        private ExpireService $passwordExpireService,
        private CurrentRoute $currentRoute,
        private TranslatorInterface $translator,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        $user = $user instanceof GuestIdentityInterface ? null : $user;
        if ($user === null || !$user instanceof User) {
            return $handler->handle($request);
        }

        $routeName = $this->currentRoute->getName();
        if ($routeName === $this->config->accountSettingsRoute || in_array($routeName, self::EXEMPT_ROUTES, true)) {
            return $handler->handle($request);
        }

        if ($this->passwordExpireService->checkPasswordExpiration($user)) {
            $response = $this->responseFactory->createResponse(302);
            return $response->withHeader('Location', $this->url->generate($this->config->accountSettingsRoute));
        }

        return $handler->handle($request);
    }
}
