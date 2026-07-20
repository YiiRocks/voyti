<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Account;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Controller\RequireUserTrait;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Account\SessionsViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Lets the logged-in user view and terminate their own active sessions; terminating the current
 * session logs the user out.
 */
final readonly class SessionController
{
    use RedirectTrait;
    use RenderTrait;
    use RequireUserTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
        private SessionInterface $session,
        private ModuleConfig $config,
        private EventDispatcherInterface $eventDispatcher,
        private FlashInterface $flash,
    ) {}

    public function index(): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        return $this->renderView('account/sessions', [
            'data' => SessionsViewData::create(
                UserSessions::findByUserId($user->getIdOrZero()),
                $this->session->getId(),
                $user->getProfile()?->getTimezone(),
                $this->config,
                $this->url,
                $this->translator(),
            ),
        ]);
    }

    public function terminate(string $sessionId): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        $userSession = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), $sessionId);
        if ($userSession === null) {
            return $this->renderError('voyti.settings.session_not_found');
        }

        $userSession->setRevokedAt(time());
        $userSession->save();
        $this->eventDispatcher->dispatch(
            new SessionEvent($user->getIdOrZero(), $sessionId, ['type' => SessionEvent::SESSION_TERMINATED]),
        );

        if ($sessionId === $this->session->getId()) {
            $this->currentUser->logout();
            return $this->redirectWithFlash($this->url->generate('voyti/session-login'), 'voyti.security.logged_out');
        }

        return $this->redirectWithFlash(
            $this->url->generate('voyti/account-sessions'),
            'voyti.settings.session_terminated',
        );
    }

}
