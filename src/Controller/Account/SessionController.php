<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Account;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Helper\Views\SessionRowView;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
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

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
        private SessionInterface $session,
        private VoytiConfig $config,
        private EventDispatcherInterface $eventDispatcher,
        private FlashNotifier $flashNotifier,
    ) {}

    public function index(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        $currentSessionId = $this->session->getId();
        $timezone = $user->getProfile()?->getTimezone();
        $locale = $this->translator()->getLocale();
        $url = $this->url;

        /** @infection-ignore-all array_values only reindexes keys after the filter; the sessions view iterates rows by value, so the reindex is immaterial. */
        $sessions = array_values(
            array_filter(
                UserSessions::findByUserId($user->getIdOrZero()),
                static fn(UserSessions $session): bool => !$session->isRevoked(),
            ),
        );

        return $this->renderView('account/sessions', [
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
                'sessions' => array_map(
                    static fn(UserSessions $session): array => [
                        'session' => SessionRowView::create($session, $timezone, $locale),
                        'isCurrentSession' => $session->getSessionId() === $currentSessionId,
                        'formSubmitUrl' => $url->generate(
                            'voyti/user-account-sessions-terminate',
                            ['sessionId' => $session->getSessionId()],
                        ),
                    ],
                    $sessions,
                ),
            ],
        ]);
    }

    public function terminate(#[RouteArgument] string $sessionId): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

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
            $this->url->generate('voyti/user-account-sessions'),
            'voyti.settings.session_terminated',
        );
    }
}
