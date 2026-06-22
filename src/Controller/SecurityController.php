<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\User\FormEvent;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\User\CurrentUser;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Http\Method;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class SecurityController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly UserRepository $userRepository,
        private readonly CurrentUser $currentUser,
        private readonly PasswordHasher $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly SessionInterface $session,
        private readonly ModuleConfig $config,
        private readonly UserSocialAuthenticateService $socialNetworkAuthenticateService,
        private readonly UserSocialAccountConnectService $socialNetworkAccountConnectService,
        private readonly UserSocialAccountRepository $userSocialAccountRepository,
        private readonly HydratorInterface $hydrator,
    ) {
    }

    public function auth(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute('provider', '');
        $clientId = $request->getAttribute('client_id', '');
        $userAttributes = $request->getQueryParams();

        $result = $this->socialNetworkAuthenticateService->run($provider, $clientId, $userAttributes);

        if ($result->isSuccess()) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.security.authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        return $this->renderView('shared/message', ['title' => $result->getMessage(), 'translator' => $this->translator]);
    }

    public function confirm(ServerRequestInterface $request): ResponseInterface
    {
        $credentials = $this->session->get('credentials');
        if ($credentials === null) {
            return $this->renderView('security/login', ['model' => new LoginForm($this->config, $this->translator), 'config' => $this->config]);
        }

        $form = new LoginForm($this->config, $this->translator);
        $form->login = $credentials['login'];
        $form->password = $credentials['pwd'];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $this->hydrator->hydrate($form, $body[$form->getFormName()] ?? $body);

            $user = $this->userRepository->findByUsernameOrEmail($form->login);

            if ($user !== null && $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                $this->session->remove('credentials');
                $this->currentUser->login($user);
                return $this->renderSuccess('voyti.security.authenticated');
            }
        }

        return $this->renderView('security/confirm', ['model' => $form, 'config' => $this->config]);
    }

    public function connect(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        $provider = $request->getAttribute('provider', '');
        $clientId = $request->getAttribute('client_id', '');
        $userAttributes = $request->getQueryParams();

        $result = $this->socialNetworkAccountConnectService->run(
            $provider,
            $clientId,
            $userAttributes,
            (int) ($identity->getId() ?? 0),
        );

        return $this->renderView('shared/message', ['title' => $result->getMessage(), 'translator' => $this->translator]);
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $form = new LoginForm($this->config, $this->translator);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $this->hydrator->hydrate($form, $body[$form->getFormName()] ?? $body);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $user = $this->userRepository->findByUsernameOrEmail($form->login);

                if ($user === null || !$this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                    $errors['login'] = $this->translator->translate('voyti.security.invalid_login', category: 'voyti');
                } elseif ($user->isBlocked()) {
                    $errors['login'] = $this->translator->translate('voyti.security.account_blocked', category: 'voyti');
                } elseif ($this->config->enableEmailConfirmation && !$user->isConfirmed()) {
                    $errors['login'] = $this->translator->translate('voyti.security.need_email_confirmation', category: 'voyti');
                } else {
                    if ($this->config->enableTwoFactorAuthentication && $user->isAuthTfEnabled()) {
                        $this->session->set('credentials', ['login' => $form->login, 'pwd' => $form->password]);
                        return $this->renderView('security/confirm', ['model' => $form, 'config' => $this->config]);
                    }

                    $userToLogin = $this->currentUser;
                    if ($form->rememberMe) {
                        $userToLogin = $userToLogin->withAuthTimeout($this->config->rememberLoginLifespan);
                    }
                    $userToLogin->login($user);
                    $user->setLastLoginAt(time());
                    $user->setLastLoginIp($this->config->disableIpLogging ? '127.0.0.1' : ($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1'));
                    $user->save();

                    $this->eventDispatcher->dispatch(new FormEvent($form));
                    $this->eventDispatcher->dispatch(new AfterLoginEvent($user));

                    return $this->renderSuccess('voyti.security.logged_in');
                }
            } else {
                $errors = $result->getErrorMessages();
            }
        }

        return $this->renderView('security/login', [
            'model' => $form,
            'config' => $this->config,
            'errors' => $errors,
        ]);
    }

    public function logout(): ResponseInterface
    {
        $this->currentUser->logout();
        return $this->renderSuccess('voyti.security.logged_out');
    }
}
