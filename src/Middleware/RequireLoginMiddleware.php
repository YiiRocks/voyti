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
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Guards a route group against guests: redirects to `voyti/session-login`, except for routes in
 * {@see JSON_RESPONSE_ROUTES}, which are called from JS and must get a JSON 401 instead.
 */
final readonly class RequireLoginMiddleware implements MiddlewareInterface
{
    /**
     * @var string[] Route names that respond with JSON rather than a redirect when unauthenticated.
     */
    private const JSON_RESPONSE_ROUTES = ['voyti/user-two-factor-renew'];

    public function __construct(
        private CurrentUser $currentUser,
        private CurrentRoute $currentRoute,
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

        if (in_array($this->currentRoute->getName(), self::JSON_RESPONSE_ROUTES, true)) {
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
