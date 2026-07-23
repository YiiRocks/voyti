<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Guards a route group against guests: redirects to `voyti/session-login`, except for requests
 * that declare `Accept: application/json` (e.g. AJAX calls), which get a JSON 401 instead.
 */
final readonly class RequireLoginMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $url,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        if (!$user instanceof GuestIdentityInterface) {
            return $handler->handle($request);
        }

        if (str_contains($request->getHeaderLine(Header::ACCEPT), 'application/json')) {
            $response = $this->responseFactory->createResponse(Status::UNAUTHORIZED)
                ->withHeader(Header::CONTENT_TYPE, 'application/json; charset=UTF-8');
            $response->getBody()->write(Json::encode([
                'error' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'),
            ]));
            return $response;
        }

        return $this->responseFactory->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $this->url->generate('voyti/session-login'));
    }
}
