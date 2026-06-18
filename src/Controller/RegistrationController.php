<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Method;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\View\ViewInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use YiiRocks\Voyti\Form\RegistrationForm;
use YiiRocks\Voyti\Form\ResendForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\RenderTrait;
use YiiRocks\Voyti\Service\UserRegisterService;
use YiiRocks\Voyti\Service\ResendConfirmationService;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\TokenRepository;
use YiiRocks\Voyti\Service\UserConfirmationService;
use YiiRocks\Voyti\Service\AccountConfirmationService;
use YiiRocks\Voyti\Event\FormEvent;

final class RegistrationController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewInterface $view,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Aliases $aliases,
        private readonly UserRegisterService $userRegisterService,
        private readonly UserRepository $userRepository,
        private readonly TokenRepository $tokenRepository,
        private readonly UserConfirmationService $userConfirmationService,
        private readonly AccountConfirmationService $accountConfirmationService,
        private readonly ResendConfirmationService $resendConfirmationService,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly ModuleConfig $config,
    ) {
    }

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableRegistration) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.registration.disabled'), 'translator' => $this->translator]);
        }

        $form = new RegistrationForm($this->config);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'register');
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $serviceResult = $this->userRegisterService->run([
                    'username' => $form->username,
                    'email' => $form->email,
                    'password' => $form->password,
                    'gdprConsent' => $form->gdprConsent,
                ]);

                if ($serviceResult->isSuccess()) {
                    $this->eventDispatcher->dispatch(new FormEvent($form));
                    return $this->renderView('shared/message', ['title' => $serviceResult->getMessage()]);
                }
                $errors = $serviceResult->getErrors();
            } else {
                $errors = $result->getErrorMessages();
            }
        }

        return $this->renderView('registration/register', [
            'model' => $form,
            'config' => $this->config,
            'errors' => $errors,
        ]);
    }

    public function confirm(ServerRequestInterface $request, int $id, string $code): ResponseInterface
    {
        $user = $this->userRepository->findById($id);

        if ($user === null || !$this->config->enableEmailConfirmation) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.registration.invalid_confirmation_link'), 'translator' => $this->translator]);
        }

        if ($this->accountConfirmationService->run($code, $user, $this->userConfirmationService)) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.registration.complete'), 'translator' => $this->translator]);
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.registration.confirmation_link_invalid'), 'translator' => $this->translator]);
    }

    public function resend(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableEmailConfirmation) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.registration.email_confirmation_disabled'), 'translator' => $this->translator]);
        }

        $form = new ResendForm($this->config);

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'resend');
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $user = $this->userRepository->findByEmail($form->email);
                if ($user !== null && $this->resendConfirmationService->run($user)) {
                    return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.registration.new_confirmation_sent'), 'translator' => $this->translator]);
                }
            }
        }

        return $this->renderView('registration/resend', ['model' => $form, 'config' => $this->config]);
    }

    public function connect(ServerRequestInterface $request, string $code): ResponseInterface
    {
        return $this->renderView('registration/connect', ['code' => $code, 'config' => $this->config]);
    }
}
