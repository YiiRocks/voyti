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
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

final class PasswordAgeEnforceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UrlGeneratorInterface $url,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $maxPasswordAge = $this->config->maxPasswordAge;
        if ($maxPasswordAge === null) {
            return $handler->handle($request);
        }

        $user = $this->currentUser->getIdentity();
        $user = $user instanceof GuestIdentityInterface ? null : $user;
        if ($user === null || !$user instanceof User) {
            return $handler->handle($request);
        }

        $passwordChangedAt = $user->getPasswordChangedAt();
        if ($passwordChangedAt !== null && (time() - $passwordChangedAt) >= $maxPasswordAge * 86400) {
            $response = $this->responseFactory->createResponse(302);
            return $response->withHeader('Location', $this->url->generate($this->config->accountSettingsRoute));
        }

        return $handler->handle($request);
    }
}
