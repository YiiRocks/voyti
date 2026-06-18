<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\IdentityServiceInterface;
use Yiisoft\Http\Method;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\View\ViewRenderer;
use YiiRocks\Voyti\Form\LoginForm;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\SocialNetworkAccountRepository;
use YiiRocks\Voyti\Service\SocialNetworkAuthenticateService;
use YiiRocks\Voyti\Service\SocialNetworkAccountConnectService;
use YiiRocks\Voyti\Event\FormEvent;
use YiiRocks\Voyti\Event\AfterLoginEvent;
use YiiRocks\Voyti\Event\UserEvent;
use YiiRocks\Voyti\Entity\User;

final class SecurityController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewRenderer $viewRenderer,
        private readonly UserRepository $userRepository,
        private readonly IdentityServiceInterface $identityService,
        private readonly SecurityHelper $securityHelper,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly SessionInterface $session,
        private readonly ModuleConfig $config,
        private readonly SocialNetworkAuthenticateService $socialNetworkAuthenticateService,
        private readonly SocialNetworkAccountConnectService $socialNetworkAccountConnectService,
        private readonly SocialNetworkAccountRepository $socialNetworkAccountRepository,
    ) {
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $form = new LoginForm($this->config);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'login');
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $user = $this->userRepository->findByUsernameOrEmail($form->login);

                if ($user === null || !$this->securityHelper->validatePassword($form->password, $user->getPasswordHash())) {
                    $errors['login'] = $this->translator->translate('voyti.security.invalid_login');
                } elseif ($user->isBlocked()) {
                    $errors['login'] = $this->translator->translate('voyti.security.account_blocked');
                } elseif ($this->config->enableEmailConfirmation && !$user->isConfirmed()) {
                    $errors['login'] = $this->translator->translate('voyti.security.need_email_confirmation');
                } else {
                    if ($this->config->enableTwoFactorAuthentication && $user->isAuthTfEnabled()) {
                        $this->session->set('credentials', ['login' => $form->login, 'pwd' => $form->password]);
                        return $this->viewRenderer->render('security/confirm', ['model' => $form, 'config' => $this->config]);
                    }

                    $this->identityService->login($user, $form->rememberMe ? $this->config->rememberLoginLifespan : null);
                    $user->setLastLoginAt(time());
                    $user->setLastLoginIp($this->config->disableIpLogging ? '127.0.0.1' : ($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1'));
                    $user->save();

                    $this->eventDispatcher->dispatch(new FormEvent($form));
                    $this->eventDispatcher->dispatch(new AfterLoginEvent($user));

                    return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.security.logged_in'), 'translator' => $this->translator]);
                }
            } else {
                $errors = $result->getErrorMessages();
            }
        }

        return $this->viewRenderer->render('security/login', [
            'model' => $form,
            'config' => $this->config,
            'errors' => $errors,
        ]);
    }

    public function logout(): ResponseInterface
    {
        $this->identityService->logout();
        return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.security.logged_out'), 'translator' => $this->translator]);
    }

    public function confirm(ServerRequestInterface $request): ResponseInterface
    {
        $credentials = $this->session->get('credentials');
        if ($credentials === null) {
            return $this->viewRenderer->render('security/login', ['model' => new LoginForm($this->config), 'config' => $this->config]);
        }

        $form = new LoginForm($this->config);
        $form->login = $credentials['login'];
        $form->password = $credentials['pwd'];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'login');

            $user = $this->userRepository->findByUsernameOrEmail($form->login);

            if ($user !== null && $this->securityHelper->validatePassword($form->password, $user->getPasswordHash())) {
                $this->session->remove('credentials');
                $this->identityService->login($user);
                return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.security.authenticated'), 'translator' => $this->translator]);
            }
        }

        return $this->viewRenderer->render('security/confirm', ['model' => $form, 'config' => $this->config]);
    }

    public function auth(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute('provider', '');
        $clientId = $request->getAttribute('client_id', '');
        $userAttributes = $request->getQueryParams();

        $result = $this->socialNetworkAuthenticateService->run($provider, $clientId, $userAttributes);

        if ($result->isSuccess()) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.security.authenticated'), 'translator' => $this->translator]);
        }

        return $this->viewRenderer->render('shared/message', ['title' => $result->getMessage(), 'translator' => $this->translator]);
    }

    public function connect(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(\Yiisoft\Auth\IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
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

        return $this->viewRenderer->render('shared/message', ['title' => $result->getMessage(), 'translator' => $this->translator]);
    }
}
