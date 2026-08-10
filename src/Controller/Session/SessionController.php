<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Session;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthCallbackService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\TwoFactor\BackupCodeService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Validator\TwoFactor\CodeValidator;
use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;
use YiiRocks\Voyti\ViewData\Session\ConfirmViewData;
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
 * Handles login, logout, and two-factor confirmation during login. The social-auth redirect/callback
 * flow itself is handled by {@see AuthAction} and
 * {@see SocialAuthCallbackService}, wired directly as the
 * `voyti/session-auth` route action.
 */
final readonly class SessionController
{
    use RedirectTrait;
    use RenderTrait;

    private const string SESSION_KEY_CREDENTIALS = 'credentials';

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
        private PendingSocialAccountService $pendingSocialAccountService,
        private FormHydrator $formHydrator,
        private EmailCodeGeneratorService $twoFactorEmailCodeService,
        private FlashNotifier $toast,
        private BackupCodeService $backupCodeService,
        private CodeValidator $codeValidator,
        private EmailValidator $emailValidator,
    ) {}

    public function confirm(ServerRequestInterface $request): ResponseInterface
    {
        /** @var mixed $credentialsValue */
        $credentialsValue = $this->session->get(self::SESSION_KEY_CREDENTIALS);
        $credentials = is_array($credentialsValue) ? $credentialsValue : [];
        if ($credentials === []) {
            $form = new LoginForm($this->config, $this->translator);

            return $this->renderView('session/login', [
                'form' => $form,
                'data' => LoginViewData::create($form, $this->config, $this->url, $this->clientCollection),
            ]);
        }

        $form = new LoginForm($this->config, $this->translator, requireTwoFactorAuthenticationCode: true);
        /** @var mixed $loginValue */
        $loginValue = $credentials['login'] ?? '';
        $form->login = is_string($loginValue) ? $loginValue : '';
        $method = User::findByUsernameOrEmail($form->login)?->getAuthTfType() ?? 'google';

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            $user = User::findByUsernameOrEmail($form->login);

            if ($user !== null) {
                $code = $form->twoFactorAuthenticationCode ?? '';

                if ($method === 'email') {
                    $isValid = $this->emailValidator->validate($user, $code);
                    $errorMessage = $this->emailValidator->getErrorMessage();
                } else {
                    $isValid = $this->codeValidator->validate($user, $code);
                    $errorMessage = $this->codeValidator->getErrorMessage();
                }

                if (!$isValid) {
                    $isValid = $this->backupCodeService->consume($user, $code);
                }

                if ($isValid) {
                    $this->session->remove(self::SESSION_KEY_CREDENTIALS);
                    $previousSessionId = $this->session->getId();
                    $currentUser = $this->boolValue($credentials, 'rememberMe')
                        ? $this->currentUser->withAuthTimeout($this->config->rememberLoginLifespan)
                        : $this->currentUser;
                    $currentUser->login($user);
                    LoginMetadataHelper::recordLogin($user, $request->getServerParams());
                    $this->pendingSocialAccountService->connect($user);
                    $this->eventDispatcher->dispatch(
                        new AfterLoginEvent(
                            $user,
                            previousSessionId: $previousSessionId,
                            serverParams: $request->getServerParams(),
                        ),
                    );

                    $response = $this->homeRedirectResponse();
                    if ($this->boolValue($credentials, 'rememberMe')) {
                        $response = $this->rememberMeCookieService->addCookie(
                            $user,
                            $response,
                            $this->session->getId() ?? '',
                        );
                    }

                    return $response;
                }

                $form->addError(
                    $errorMessage !== ''
                        ? $errorMessage
                        : $this->translator->translate('voyti.validator.invalid_verification_code', category: 'voyti'),
                    ['twoFactorAuthenticationCode'],
                );
            }
        }

        return $this->renderView('session/confirm', ['form' => $form, 'data' => ConfirmViewData::create($method, $this->url)]);
    }

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
                if ($this->config->enableTwoFactorAuthentication && $user->isAuthTfEnabled()) {
                    if ($user->getAuthTfType() === 'email') {
                        $this->twoFactorEmailCodeService->run($user);
                    }

                    $this->session->set(self::SESSION_KEY_CREDENTIALS, [
                        'login' => $form->login,
                        'rememberMe' => $form->rememberMe,
                    ]);
                    return $this->renderView('session/confirm', [
                        'form' => $form,
                        'data' => ConfirmViewData::create($user->getAuthTfType() ?? 'google', $this->url),
                    ]);
                }

                $previousSessionId = $this->session->getId();
                $userToLogin = $this->currentUser;
                if ($form->rememberMe) {
                    $userToLogin = $userToLogin->withAuthTimeout($this->config->rememberLoginLifespan);
                }
                $userToLogin->login($user);
                LoginMetadataHelper::recordLogin($user, $request->getServerParams());
                $this->pendingSocialAccountService->connect($user);

                $this->eventDispatcher->dispatch(
                    new AfterLoginEvent(
                        $user,
                        previousSessionId: $previousSessionId,
                        serverParams: $request->getServerParams(),
                    ),
                );

                $response = $this->homeRedirectResponse();
                if ($form->rememberMe) {
                    $response = $this->rememberMeCookieService->addCookie(
                        $user,
                        $response,
                        $this->session->getId() ?? '',
                    );
                }

                return $response;
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

    /**
     * @param array<array-key, mixed> $data
     */
    private function boolValue(array $data, string $key): bool
    {
        /**
         * @var mixed $value
         *
         * @infection-ignore-all The only caller stores 'rememberMe' explicitly, so the missing-key default is never reached.
         */
        $value = $data[$key] ?? false;
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        /** @infection-ignore-all Defensive fallback for values filter_var can't parse; the form only ever sends parseable booleans, and a truthy leftover string casts identically. */
        return $boolValue ?? (bool) $value;
    }

    private function homeRedirectResponse(): ResponseInterface
    {
        return $this->redirect($this->config->getHomeUrl($this->url));
    }
}
