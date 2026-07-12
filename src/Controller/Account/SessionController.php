<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Account;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessionHistory;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class SessionController
{
    use RedirectTrait;
    use RenderTrait;

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
    ) {
    }

    public function index(): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        return $this->renderView('account/sessions', [
            'sessions' => $this->config->enableSessionHistory ? UserSessionHistory::findByUserId($user->getIdOrZero()) : [],
            'currentSessionId' => $this->session->getId(),
            'config' => $this->config,
            'flash' => $this->flash,
        ]);
    }

    public function terminate(string $sessionId): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if (!$this->config->enableSessionHistory) {
            return $this->renderError('voyti.settings.session_history_disabled');
        }

        $sessionHistory = UserSessionHistory::findByUserIdAndSessionId($user->getIdOrZero(), $sessionId);
        if ($sessionHistory === null) {
            return $this->renderError('voyti.settings.session_not_found');
        }

        $sessionHistory->delete();
        $this->eventDispatcher->dispatch(
            new SessionEvent($user->getIdOrZero(), $sessionId, ['type' => SessionEvent::SESSION_TERMINATED]),
        );

        if ($sessionId === $this->session->getId()) {
            $this->currentUser->logout();
            return $this->redirectWithFlash($this->url->generate('voyti/session-login'), 'voyti.security.logged_out');
        }

        return $this->redirectWithFlash($this->url->generate('voyti/account-sessions'), 'voyti.settings.session_terminated');
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }

    private function requireUser(): User|ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        $user = User::findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderError('voyti.settings.user_not_found');
        }

        return $user;
    }
}
