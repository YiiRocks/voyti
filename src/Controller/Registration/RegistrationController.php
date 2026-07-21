<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Registration;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\ViewData\Registration\ConnectViewData;
use YiiRocks\Voyti\ViewData\Registration\RegisterViewData;
use YiiRocks\Voyti\ViewData\Registration\ResendViewData;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles new-account registration: the registration form, email confirmation, resending the
 * confirmation email, and connecting a pending social account created during signup.
 */
final readonly class RegistrationController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private RegisterService $userRegisterService,
        private ConfirmationService $confirmationService,
        private ValidatorInterface $validator,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private PendingSocialAccountService $pendingSocialAccountService,
        private HydratorInterface $hydrator,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
        private AuthClientRegistry $authClientRegistry,
    ) {}

    public function confirm(ServerRequestInterface $request, #[RouteArgument] int $id, #[RouteArgument] string $code): ResponseInterface
    {
        $user = User::findById($id);

        if ($user === null || !$this->config->enableEmailConfirmation) {
            return $this->renderError('voyti.registration.invalid_confirmation_link');
        }

        if ($user->isConfirmed()) {
            return $this->redirectWithFlash($this->url->generate('voyti/session-login'), 'voyti.registration.complete');
        }

        if ($this->confirmationService->confirmWithCode($code, $user)) {
            return $this->redirectWithFlash($this->url->generate('voyti/session-login'), 'voyti.registration.complete');
        }

        return $this->renderError('voyti.registration.confirmation_link_invalid');
    }

    public function connect(ServerRequestInterface $request, #[RouteArgument] string $code): ResponseInterface
    {
        $account = $this->pendingSocialAccountService->useCode($code);
        if ($account === null) {
            return $this->renderError('voyti.settings.network_not_found');
        }

        return $this->renderView('registration/connect', [
            'data' => ConnectViewData::create($account, $this->authClientRegistry, $this->url),
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
                $serviceResult = $this->userRegisterService->run(
                    [
                        'username' => $form->username,
                        'email' => $form->email,
                        'password' => $form->password,
                        'gdprConsent' => $form->gdprConsent,
                    ],
                    $request->getServerParams(),
                );

                if ($serviceResult->isSuccess()) {
                    $user = User::findByEmail($form->email);
                    if ($user !== null) {
                        $this->pendingSocialAccountService->connect($user);
                    }

                    return $this->redirectWithFlash(
                        $this->url->generate('voyti/session-login'),
                        $serviceResult->getMessage(),
                    );
                }
                $errors = $serviceResult->getErrors();
                array_walk(
                    $errors,
                    function (mixed $error) use ($form): void {
                        if (is_string($error)) {
                            $form->addError($error, []);
                        }
                    },
                );
            }
        }

        return $this->renderView('registration/register', [
            'form' => $form,
            'data' => RegisterViewData::create($form, $this->config, $this->url),
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
                $user = User::findByEmail($form->email);
                if ($user !== null && $this->confirmationService->resend($user)) {
                    return $this->redirectWithFlash(
                        $this->url->generate('voyti/session-login'),
                        'voyti.registration.new_confirmation_sent',
                    );
                }
            }
        }

        return $this->renderView('registration/resend', [
            'form' => $form,
            'data' => ResendViewData::create($form, $this->config, $this->url),
        ]);
    }

}
