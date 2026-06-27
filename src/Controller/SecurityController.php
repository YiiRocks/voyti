<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\User\FormEvent;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class SecurityController
{
    use InputDataTrait;
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly UserRepository $userRepository,
        private readonly CurrentUser $currentUser,
        private readonly PasswordHasher $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UrlGeneratorInterface $url,
        private readonly SessionInterface $session,
        private readonly ModuleConfig $config,
        private readonly AuthClientRegistry $authClientRegistry,
        private readonly SocialAuthProviderService $socialAuthProviderService,
        private readonly PendingSocialAccountService $pendingSocialAccountService,
        private readonly UserSocialAuthenticateService $socialNetworkAuthenticateService,
        private readonly UserSocialAccountConnectService $socialNetworkAccountConnectService,
        private readonly HydratorInterface $hydrator,
    ) {
    }

    public function auth(ServerRequestInterface $request, string $provider): ResponseInterface
    {
        /** @var array<string, mixed> $queryParams */
        $queryParams = $this->queryParams($request);

        try {
            if (!$this->socialAuthProviderService->hasCallbackParameters($queryParams)) {
                return $this->redirect($this->socialAuthProviderService->begin($provider, 'voyti/auth'));
            }

            $identity = $this->socialAuthProviderService->complete($provider, 'voyti/auth', $queryParams);
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

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.security.authenticated', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function confirm(ServerRequestInterface $request): ResponseInterface
    {
        $credentials = $this->sessionArray($this->session, 'credentials');
        if ($credentials === []) {
            return $this->renderView('security/login', [
                'model' => new LoginForm($this->config, $this->translator),
                'config' => $this->config,
                'authClients' => $this->authClientRegistry,
            ]);
        }

        $form = new LoginForm($this->config, $this->translator);
        $form->login = $this->stringValue($credentials, 'login');
        $form->password = $this->stringValue($credentials, 'pwd');

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));

            $user = $this->userRepository->findByUsernameOrEmail($form->login);

            if ($user !== null && $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                $this->session->remove('credentials');
                $currentUser = $this->boolValue($credentials, 'rememberMe')
                    ? $this->currentUser->withAuthTimeout($this->config->rememberLoginLifespan)
                    : $this->currentUser;
                $currentUser->login($user);
                $this->updateLastLoginMetadata($user, $request->getServerParams());
                $this->pendingSocialAccountService->connect($user);
                $this->eventDispatcher->dispatch(new AfterLoginEvent($user));
                return $this->renderSuccess('voyti.security.authenticated');
            }
        }

        return $this->renderView('security/confirm', ['model' => $form, 'config' => $this->config]);
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
                return $this->redirect($this->socialAuthProviderService->begin($provider, 'voyti/connect'));
            }

            $attributes = $this->socialAuthProviderService->complete($provider, 'voyti/connect', $queryParams);
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

        return $this->renderView('shared/message', ['title' => $result->isSuccess() ? $this->translator->translate('voyti.security.authenticated', category: 'voyti') : $result->getMessage(), 'translator' => $this->translator]);
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $form = new LoginForm($this->config, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);
            $form->processValidationResult($result);

            if ($result->isValid()) {
                $user = $this->userRepository->findByUsernameOrEmail($form->login);

                if ($user === null || !$this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                    $form->addError($this->translator->translate('voyti.security.invalid_login', category: 'voyti'), ['login']);
                } elseif ($user->isBlocked()) {
                    $form->addError($this->translator->translate('voyti.security.account_blocked', category: 'voyti'), ['login']);
                } elseif ($this->config->enableEmailConfirmation && !$user->isConfirmed()) {
                    $form->addError($this->translator->translate('voyti.security.need_email_confirmation', category: 'voyti'), ['login']);
                } else {
                    if ($this->config->enableTwoFactorAuthentication && $user->isAuthTfEnabled()) {
                        $this->session->set('credentials', [
                            'login' => $form->login,
                            'pwd' => $form->password,
                            'rememberMe' => $form->rememberMe,
                        ]);
                        return $this->renderView('security/confirm', ['model' => $form, 'config' => $this->config]);
                    }

                    $userToLogin = $this->currentUser;
                    if ($form->rememberMe) {
                        $userToLogin = $userToLogin->withAuthTimeout($this->config->rememberLoginLifespan);
                    }
                    $userToLogin->login($user);
                    $this->updateLastLoginMetadata($user, $request->getServerParams());
                    $this->pendingSocialAccountService->connect($user);

                    $this->eventDispatcher->dispatch(new FormEvent($form));
                    $this->eventDispatcher->dispatch(new AfterLoginEvent($user));

                    return $this->renderSuccess('voyti.security.logged_in');
                }
            }
        }

        return $this->renderView('security/login', [
            'model' => $form,
            'config' => $this->config,
            'authClients' => $this->authClientRegistry,
        ]);
    }

    public function logout(): ResponseInterface
    {
        $this->currentUser->logout();
        return $this->renderSuccess('voyti.security.logged_out');
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
        $value = $data[$key] ?? false;
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $boolValue ?? (bool) $value;
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    private function remoteAddr(array $serverParams): string
    {
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddr) && $remoteAddr !== '' ? $remoteAddr : '127.0.0.1';
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    private function updateLastLoginMetadata(User $user, array $serverParams): void
    {
        $user->setLastLoginAt(time());
        $user->setLastLoginIp($this->config->disableIpLogging ? '127.0.0.1' : $this->remoteAddr($serverParams));
        $user->save();
    }

    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(302)
            ->withHeader('Location', $url);
    }
}
