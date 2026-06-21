<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Event\User\FormEvent;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Form\Auth\ResendForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\User\AccountConfirmationService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\ResendConfirmationService;
use Yiisoft\Http\Method;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class RegistrationController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly RegisterService $userRegisterService,
        private readonly UserRepository $userRepository,
        private readonly UserTokenRepository $userTokenRepository,
        private readonly ConfirmationService $userConfirmationService,
        private readonly AccountConfirmationService $accountConfirmationService,
        private readonly ResendConfirmationService $resendConfirmationService,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly ModuleConfig $config,
    ) {
    }

    public function confirm(ServerRequestInterface $request, int $id, string $code): ResponseInterface
    {
        $user = $this->userRepository->findById($id);

        if ($user === null || !$this->config->enableEmailConfirmation) {
            return $this->renderError('voyti.registration.invalid_confirmation_link');
        }

        if ($this->accountConfirmationService->run($code, $user, $this->userConfirmationService)) {
            return $this->renderSuccess('voyti.registration.complete');
        }

        return $this->renderError('voyti.registration.confirmation_link_invalid');
    }

    public function connect(ServerRequestInterface $request, string $code): ResponseInterface
    {
        return $this->renderView('registration/connect', ['code' => $code, 'config' => $this->config]);
    }

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableRegistration) {
            return $this->renderError('voyti.registration.disabled');
        }

        $form = new RegistrationForm($this->config, $this->translator);
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

    public function resend(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableEmailConfirmation) {
            return $this->renderError('voyti.registration.email_confirmation_disabled');
        }

        $form = new ResendForm($this->config, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'resend');
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $user = $this->userRepository->findByEmail($form->email);
                if ($user !== null && $this->resendConfirmationService->run($user)) {
                    return $this->renderSuccess('voyti.registration.new_confirmation_sent');
                }
            }
        }

        return $this->renderView('registration/resend', ['model' => $form, 'config' => $this->config]);
    }
}
