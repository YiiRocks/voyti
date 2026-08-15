<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Auth\PostLoginHookInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

/**
 * Finalizes an authenticated login once every check (password, and any login challenge such as
 * two-factor) has passed: establishes the session, records login metadata, runs every
 * {@see PostLoginHookInterface} (collected via the `voyti.post-login-hook` tag - e.g. connecting a
 * pending social account from `yiirocks/voyti-social-auth`), dispatches {@see AfterLoginEvent}, and
 * redirects home with an optional remember-me cookie. Shared by the plain login flow and by challenge
 * handlers that complete login after their own step.
 */
final readonly class LoginCompletionService
{
    public function __construct(
        private CurrentUser $currentUser,
        private EventDispatcherInterface $eventDispatcher,
        private ResponseFactoryInterface $responseFactory,
        private RememberMeCookieService $rememberMeCookieService,
        /** @var iterable<PostLoginHookInterface> */
        private iterable $postLoginHooks,
        private SessionInterface $session,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
    ) {}

    public function complete(User $user, bool $rememberMe, ServerRequestInterface $request): ResponseInterface
    {
        $previousSessionId = $this->session->getId();
        $currentUser = $rememberMe
            ? $this->currentUser->withAuthTimeout($this->config->rememberLoginLifespan)
            : $this->currentUser;
        $currentUser->login($user);
        LoginMetadataHelper::recordLogin($user, $request->getServerParams());
        foreach ($this->postLoginHooks as $postLoginHook) {
            $postLoginHook->handle($user);
        }
        $this->eventDispatcher->dispatch(
            new AfterLoginEvent(
                $user,
                previousSessionId: $previousSessionId,
                serverParams: $request->getServerParams(),
            ),
        );

        $response = $this->responseFactory->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $this->config->getHomeUrl($this->url));
        if ($rememberMe) {
            $response = $this->rememberMeCookieService->addCookie($user, $response, $this->session->getId() ?? '');
        }

        return $response;
    }
}
