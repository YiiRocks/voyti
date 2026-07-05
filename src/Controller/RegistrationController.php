<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Event\User\FormEvent;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Form\Auth\ResendForm;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\User\AccountConfirmationService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\ResendConfirmationService;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class RegistrationController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private RegisterService $userRegisterService,
        private UserRepository $userRepository,
        private UserTokenRepository $userTokenRepository,
        private ConfirmationService $userConfirmationService,
        private AccountConfirmationService $accountConfirmationService,
        private ResendConfirmationService $resendConfirmationService,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private PendingSocialAccountService $pendingSocialAccountService,
        private HydratorInterface $hydrator,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
    ) {
    }

    public function confirm(ServerRequestInterface $request, int $id, string $code): ResponseInterface
    {
        $user = $this->userRepository->findById($id);

        if ($user === null || !$this->config->enableEmailConfirmation) {
            return $this->renderError('voyti.registration.invalid_confirmation_link');
        }

        if ($user->isConfirmed()) {
            return $this->redirectWithFlash($this->url->generate($this->config->loginRoute), 'voyti.registration.complete');
        }

        if ($this->accountConfirmationService->run($code, $user, $this->userConfirmationService)) {
            return $this->redirectWithFlash($this->url->generate($this->config->loginRoute), 'voyti.registration.complete');
        }

        return $this->renderError('voyti.registration.confirmation_link_invalid');
    }

    public function connect(ServerRequestInterface $request, string $code): ResponseInterface
    {
        $account = $this->pendingSocialAccountService->useCode($code);
        if ($account === null) {
            return $this->renderError('voyti.settings.network_not_found');
        }

        return $this->renderView('registration/connect', [
            'account' => $account,
        ]);
    }

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableRegistration) {
            return $this->renderError('voyti.registration.disabled');
        }

        $form = new RegistrationForm($this->config, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);
            $form->processValidationResult($result);

            if ($result->isValid()) {
                $serviceResult = $this->userRegisterService->run([
                    'username' => $form->username,
                    'email' => $form->email,
                    'password' => $form->password,
                    'gdprConsent' => $form->gdprConsent,
                ]);

                if ($serviceResult->isSuccess()) {
                    $user = $this->userRepository->findByEmail($form->email);
                    if ($user !== null) {
                        $this->pendingSocialAccountService->connect($user);
                    }
                    $this->eventDispatcher->dispatch(new FormEvent($form));

                    return $this->redirectWithFlash(
                        $this->url->generate($this->config->loginRoute),
                        $serviceResult->getMessage(),
                    );
                }
                foreach ($serviceResult->getErrors() as $error) {
                    if (is_string($error)) {
                        $form->addError($error, []);
                    }
                }
            }
        }

        return $this->renderView('registration/register', [
            'model' => $form,
            'config' => $this->config,
        ]);
    }

    public function resend(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableEmailConfirmation) {
            return $this->renderError('voyti.registration.email_confirmation_disabled');
        }

        $form = new ResendForm($this->config, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $user = $this->userRepository->findByEmail($form->email);
                if ($user !== null && $this->resendConfirmationService->run($user)) {
                    return $this->redirectWithFlash(
                        $this->url->generate($this->config->loginRoute),
                        'voyti.registration.new_confirmation_sent',
                    );
                }
            }
        }

        return $this->renderView('registration/resend', ['model' => $form, 'config' => $this->config]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }
}
