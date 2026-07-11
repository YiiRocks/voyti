<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Session;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\User\FormEvent;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Validator\TwoFactor\CodeValidator;
use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class SessionController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    private const string SESSION_KEY_CREDENTIALS = 'credentials';

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private PasswordHasher $passwordHasher,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
        private SessionInterface $session,
        private RememberMeCookieService $rememberMeCookieService,
        private ModuleConfig $config,
        private AuthClientRegistry $authClientRegistry,
        private SocialAuthProviderService $socialAuthProviderService,
        private PendingSocialAccountService $pendingSocialAccountService,
        private UserSocialAuthenticateService $socialNetworkAuthenticateService,
        private UserSocialAccountConnectService $socialNetworkAccountConnectService,
        private HydratorInterface $hydrator,
        private EmailCodeGeneratorService $twoFactorEmailCodeService,
        private FlashInterface $flash,
    ) {
    }

    public function auth(ServerRequestInterface $request, string $provider): ResponseInterface
    {
        /** @var array<string, mixed> $queryParams */
        $queryParams = $this->queryParams($request);

        try {
            if (!$this->socialAuthProviderService->hasCallbackParameters($queryParams)) {
                return $this->redirect($this->socialAuthProviderService->begin($provider, 'voyti/session-auth'));
            }

            $identity = $this->socialAuthProviderService->complete($provider, 'voyti/session-auth', $queryParams);
            /** @var mixed $clientId */
            $clientId = $identity['id'] ?? '';
            $result = $this->socialNetworkAuthenticateService->run(
                $provider,
                is_string($clientId) ? $clientId : '',
                $identity,
                $request->getServerParams(),
            );
        } catch (RuntimeException $exception) {
            return $this->renderView('shared/message', ['title' => $exception->getMessage(), 'translator' => $this->translator]);
        }

        if ($result->isFailure()) {
            return $this->renderView('shared/message', ['title' => $result->getMessage(), 'translator' => $this->translator]);
        }

        $account = $this->pendingSocialAccountService->getPendingAccount();
        if ($account !== null) {
            return $this->redirect($this->url->generate('voyti/registration-connect', ['code' => $account->getCode() ?? 'connect']));
        }

        $user = $this->currentUser->getIdentity();
        if ($user instanceof User) {
            return $this->rememberMeCookieService->addCookie($user, $this->homeRedirectResponse());
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.security.authenticated', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function confirm(ServerRequestInterface $request): ResponseInterface
    {
        $credentials = $this->sessionArray($this->session, self::SESSION_KEY_CREDENTIALS);
        if ($credentials === []) {
            return $this->renderView('session/login', [
                'model' => new LoginForm($this->config, $this->translator),
                'config' => $this->config,
                'authClients' => $this->authClientRegistry,
                'flash' => $this->flash,
            ]);
        }

        $form = new LoginForm($this->config, $this->translator, requireTwoFactorAuthenticationCode: true);
        $form->login = $this->stringValue($credentials, 'login');
        $form->password = $this->stringValue($credentials, 'pwd');
        $method = User::findByUsernameOrEmail($form->login)?->getAuthTfType() ?? 'google';

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $form->processValidationResult($this->validator->validate($form));

            $user = User::findByUsernameOrEmail($form->login);

            if ($user !== null && $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                $code = $form->twoFactorAuthenticationCode ?? '';

                if ($method === 'email') {
                    $emailValidator = new EmailValidator($user, $code);
                    $isValid = $emailValidator->validate();
                    $errorMessage = $emailValidator->getErrorMessage();
                } else {
                    $codeValidator = new CodeValidator($user, $code);
                    $codeValidator->setTranslator($this->translator);
                    $isValid = $codeValidator->validate();
                    $errorMessage = $codeValidator->getErrorMessage();
                }

                if ($isValid) {
                    $this->session->remove(self::SESSION_KEY_CREDENTIALS);
                    $currentUser = $this->boolValue($credentials, 'rememberMe')
                        ? $this->currentUser->withAuthTimeout($this->config->rememberLoginLifespan)
                        : $this->currentUser;
                    $currentUser->login($user);
                    LoginMetadataHelper::recordLogin($user, $request->getServerParams(), $this->config);
                    $this->pendingSocialAccountService->connect($user);
                    $this->eventDispatcher->dispatch(new AfterLoginEvent($user));

                    $response = $this->homeRedirectResponse();
                    if ($this->boolValue($credentials, 'rememberMe')) {
                        $response = $this->rememberMeCookieService->addCookie($user, $response);
                    }

                    return $response;
                }

                $form->addError(
                    $errorMessage !== '' ? $errorMessage : $this->translator->translate('voyti.validator.invalid_verification_code', category: 'voyti'),
                    ['twoFactorAuthenticationCode'],
                );
            }
        }

        return $this->renderView('session/confirm', ['model' => $form, 'method' => $method]);
    }

    public function connect(ServerRequestInterface $request, string $provider): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        /** @var array<string, mixed> $queryParams */
        $queryParams = $this->queryParams($request);

        try {
            if (!$this->socialAuthProviderService->hasCallbackParameters($queryParams)) {
                return $this->redirect($this->socialAuthProviderService->begin($provider, 'voyti/session-connect'));
            }

            $attributes = $this->socialAuthProviderService->complete($provider, 'voyti/session-connect', $queryParams);
            /** @var mixed $clientId */
            $clientId = $attributes['id'] ?? '';
            $result = $this->socialNetworkAccountConnectService->run(
                $provider,
                is_string($clientId) ? $clientId : '',
                $attributes,
                (int) ($identity->getId() ?? 0),
            );
        } catch (RuntimeException $exception) {
            return $this->renderView('shared/message', ['title' => $exception->getMessage(), 'translator' => $this->translator]);
        }

        return $this->renderView('shared/message', ['title' => $result->isSuccess() ? $this->translator->translate('voyti.security.authenticated', category: 'voyti') : $result->getMessage()]);
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->currentUser->getIdentity() instanceof GuestIdentityInterface) {
            return $this->homeRedirectResponse();
        }

        $form = new LoginForm($this->config, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);
            $form->processValidationResult($result);

            if ($result->isValid()) {
                $user = User::findByUsernameOrEmail($form->login);

                if ($user === null || !$this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                    $form->addError($this->translator->translate('voyti.security.invalid_login', category: 'voyti'), ['login']);
                } elseif ($user->isBlocked()) {
                    $form->addError($this->translator->translate('voyti.security.account_blocked', category: 'voyti'), ['login']);
                } elseif ($this->config->enableEmailConfirmation && !$user->isConfirmed()) {
                    $form->addError($this->translator->translate('voyti.security.need_email_confirmation', category: 'voyti'), ['login']);
                } else {
                    if ($this->config->enableTwoFactorAuthentication && $user->isAuthTfEnabled()) {
                        if ($user->getAuthTfType() === 'email') {
                            $this->twoFactorEmailCodeService->run($user);
                        }

                        $this->session->set(self::SESSION_KEY_CREDENTIALS, [
                            'login' => $form->login,
                            'pwd' => $form->password,
                            'rememberMe' => $form->rememberMe,
                        ]);
                        return $this->renderView('session/confirm', [
                            'model' => $form,
                            'method' => $user->getAuthTfType() ?? 'google',
                        ]);
                    }

                    $userToLogin = $this->currentUser;
                    if ($form->rememberMe) {
                        $userToLogin = $userToLogin->withAuthTimeout($this->config->rememberLoginLifespan);
                    }
                    $userToLogin->login($user);
                    LoginMetadataHelper::recordLogin($user, $request->getServerParams(), $this->config);
                    $this->pendingSocialAccountService->connect($user);

                    $this->eventDispatcher->dispatch(new FormEvent($form));
                    $this->eventDispatcher->dispatch(new AfterLoginEvent($user));

                    $response = $this->homeRedirectResponse();
                    if ($form->rememberMe) {
                        $response = $this->rememberMeCookieService->addCookie($user, $response);
                    }

                    return $response;
                }
            }
        }

        return $this->renderView('session/login', [
            'model' => $form,
            'config' => $this->config,
            'authClients' => $this->authClientRegistry,
            'flash' => $this->flash,
        ]);
    }

    public function logout(): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($this->currentUser->logout() && $identity instanceof User) {
            $identity->setAuthKey(Random::string());
            $identity->setUpdatedAt(time());
            $identity->save();
        }

        return $this->rememberMeCookieService->expireCookie(
            $this->redirectWithFlash($this->config->getHomeUrl($this->url), 'voyti.security.logged_out'),
        );
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function boolValue(array $data, string $key): bool
    {
        /** @var mixed $value */
        $value = $data[$key] ?? false;
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $boolValue ?? (bool) $value;
    }

    private function homeRedirectResponse(): ResponseInterface
    {
        return $this->redirect($this->config->getHomeUrl($this->url));
    }
}
