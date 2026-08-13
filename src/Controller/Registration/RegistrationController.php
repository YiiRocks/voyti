<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Registration;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles new-account registration: the registration form, email confirmation, resending the
 * confirmation email, and connecting a pending social account created during signup.
 */
final readonly class RegistrationController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private RegisterService $userRegisterService,
        private ConfirmationService $confirmationService,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private PendingSocialAccountService $pendingSocialAccountService,
        private FormHydrator $formHydrator,
        private ResponseFactoryInterface $responseFactory,
        private FlashNotifier $flashNotifier,
        private ?Collection $clientCollection,
    ) {}

    public function confirm(#[RouteArgument] int $id, #[RouteArgument] string $code): ResponseInterface
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

    public function connect(#[RouteArgument] string $code): ResponseInterface
    {
        $account = $this->pendingSocialAccountService->useCode($code);
        if ($account === null) {
            return $this->renderError('voyti.settings.network_not_found');
        }

        $provider = $account->getProvider();
        $providerTitle = $this->clientCollection?->hasClient($provider) === true
            ? $this->clientCollection->getClient($provider)->getTitle()
            : $provider;

        return $this->renderView('registration/connect', [
            'data' => [
                'providerTitle' => $providerTitle,
                'loginUrl' => $this->url->generate('voyti/session-login'),
                'registerUrl' => $this->url->generate('voyti/registration-register'),
            ],
        ]);
    }

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableRegistration) {
            return $this->renderError('voyti.registration.disabled');
        }

        $form = new RegistrationForm($this->config, $this->translator);

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
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

        return $this->renderView('registration/register', [
            'form' => $form,
            'data' => [
                'formSubmitUrl' => $this->url->generate('voyti/registration-register'),
                'loginUrl' => $this->url->generate('voyti/session-login'),
                'showGdprConsent' => $this->config->enableGdprCompliance,
                'recaptchaFieldHtml' => RecaptchaHelper::render($form, $this->config),
            ],
        ]);
    }

    public function resend(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableEmailConfirmation) {
            return $this->renderError('voyti.registration.email_confirmation_disabled');
        }

        $form = new ResendForm($this->config, $this->translator);

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            $user = User::findByEmail($form->email);
            if ($user !== null && $this->confirmationService->resend($user)) {
                return $this->redirectWithFlash(
                    $this->url->generate('voyti/session-login'),
                    'voyti.registration.new_confirmation_sent',
                );
            }
        }

        return $this->renderView('registration/resend', [
            'form' => $form,
            'data' => [
                'formSubmitUrl' => $this->url->generate('voyti/registration-resend'),
                'recaptchaFieldHtml' => RecaptchaHelper::render($form, $this->config),
            ],
        ]);
    }
}
