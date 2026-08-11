<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Session;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Auth\LoginChallengeInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
use YiiRocks\Voyti\Service\Auth\SocialAuthCallbackService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\ViewData\Session\LoginViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles login and logout. A successful password login can be interrupted by a
 * {@see LoginChallengeInterface} (collected via the `voyti.login-challenge` tag - e.g. the two-factor
 * step from `yiirocks/voyti-2fa`) before the session is established. The social-auth redirect/callback
 * flow itself is handled by {@see AuthAction} and {@see SocialAuthCallbackService}, wired directly as
 * the `voyti/session-auth` route action.
 */
final readonly class SessionController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private PasswordHasher $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
        private SessionInterface $session,
        private RememberMeCookieService $rememberMeCookieService,
        private VoytiConfig $config,
        private ?Collection $clientCollection,
        private LoginCompletionService $loginCompletionService,
        /** @var iterable<LoginChallengeInterface> */
        private iterable $loginChallenges,
        private FormHydrator $formHydrator,
        private FlashNotifier $flashNotifier,
    ) {}

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->currentUser->getIdentity() instanceof GuestIdentityInterface) {
            return $this->homeRedirectResponse();
        }

        $form = new LoginForm($this->config, $this->translator);

        if ($this->formHydrator->populateFromPostAndValidate($form, $request, map: ['rememberMe' => 'rememberMe'])) {
            $user = User::findByUsernameOrEmail($form->login);

            if ($user === null || !$this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                $form->addError(
                    $this->translator->translate('voyti.security.invalid_login', category: 'voyti'),
                    ['login'],
                );
            } elseif ($user->isBlocked()) {
                $form->addError(
                    $this->translator->translate('voyti.security.account_blocked', category: 'voyti'),
                    ['login'],
                );
            } elseif ($this->config->enableEmailConfirmation && !$user->isConfirmed()) {
                $form->addError(
                    $this->translator->translate('voyti.security.need_email_confirmation', category: 'voyti'),
                    ['login'],
                );
            } else {
                foreach ($this->loginChallenges as $loginChallenge) {
                    $response = $loginChallenge->challenge($user, $form->rememberMe, $request);
                    if ($response !== null) {
                        return $response;
                    }
                }

                return $this->loginCompletionService->complete($user, $form->rememberMe, $request);
            }
        }

        return $this->renderView('session/login', [
            'form' => $form,
            'data' => LoginViewData::create($form, $this->config, $this->url, $this->clientCollection),
        ]);
    }

    public function logout(): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        $sessionId = $this->session->getId() ?? '';
        /** @infection-ignore-all Every non-guest identity is a User and every guest fails logout(), so the && vs || operator is unobservable. */
        if ($this->currentUser->logout() && $identity instanceof User) {
            if ($sessionId !== '') {
                $userId = $identity->getIdOrZero();
                $userSession = UserSessions::findByUserIdAndSessionId($userId, $sessionId);
                if ($userSession !== null) {
                    $userSession->setRevokedAt(time());
                    $userSession->save();
                    $this->eventDispatcher->dispatch(
                        new SessionEvent($userId, $sessionId, ['type' => SessionEvent::SESSION_TERMINATED]),
                    );
                }
            }

            $identity->setAuthKey(Random::string());
            $identity->setUpdatedAt(time());
            $identity->save();
        }

        return $this->rememberMeCookieService->expireCookie(
            $this->redirectWithFlash($this->config->getHomeUrl($this->url), 'voyti.security.logged_out'),
        );
    }

    private function homeRedirectResponse(): ResponseInterface
    {
        return $this->redirect($this->config->getHomeUrl($this->url));
    }
}
