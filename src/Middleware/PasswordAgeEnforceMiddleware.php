<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Auth\IdentityServiceInterface;
use Yiisoft\Translator\TranslatorInterface;
use YiiRocks\Voyti\ModuleConfig;

final class PasswordAgeEnforceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly IdentityServiceInterface $identityService,
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $maxPasswordAge = $this->config->maxPasswordAge;
        if ($maxPasswordAge === null) {
            return $handler->handle($request);
        }

        $user = $this->identityService->getIdentity();
        if ($user === null) {
            return $handler->handle($request);
        }

        $passwordChangedAt = $user->getPasswordChangedAt();
        if ($passwordChangedAt !== null && (time() - $passwordChangedAt) >= $maxPasswordAge * 86400) {
            $response = $this->responseFactory->createResponse(302);
            return $response->withHeader('Location', '/user/settings/account');
        }

        return $handler->handle($request);
    }
}
