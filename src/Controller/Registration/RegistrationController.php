<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Registration;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Auth\PostRegistrationHookInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Auth\BeforeRegisterFormValidationEvent;
use YiiRocks\Voyti\Event\Auth\RegisterFormValidationFailedEvent;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles new-account registration: the registration form, email confirmation, and resending the
 * confirmation email. A successful registration is followed by a
 * {@see PostRegistrationHookInterface} sweep (collected via the `voyti.post-registration-hook` tag -
 * e.g. connecting a pending social account from `yiirocks/voyti-social-auth`), so core needs no
 * knowledge of what packages hook into it.
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
        /** @var iterable<PostRegistrationHookInterface> */
        private iterable $postRegistrationHooks,
        private FormHydrator $formHydrator,
        private ResponseFactoryInterface $responseFactory,
        private FlashNotifier $flashNotifier,
        private EventDispatcherInterface $eventDispatcher,
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

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableRegistration) {
            return $this->renderError('voyti.registration.disabled');
        }

        $form = new RegistrationForm($this->config, $this->translator);
        $serverParams = $request->getServerParams();

        if ($this->formHydrator->populateFromPost($form, $request)) {
            $formData = $this->parsedBody($request);
            $this->eventDispatcher->dispatch(new BeforeRegisterFormValidationEvent($formData, $serverParams));
            $validationResult = $this->formHydrator->validate($form);

            if ($validationResult->isValid()) {
                $serviceResult = $this->userRegisterService->run(
                    [
                        'username' => $form->username,
                        'email' => $form->email,
                        'password' => $form->password,
                    ],
                    $serverParams,
                );

                if ($serviceResult->isSuccess()) {
                    $user = User::findByEmail($form->email);
                    if ($user !== null) {
                        foreach ($this->postRegistrationHooks as $postRegistrationHook) {
                            $postRegistrationHook->handle($user);
                        }
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
            } else {
                $this->eventDispatcher->dispatch(
                    new RegisterFormValidationFailedEvent($formData, $validationResult->getErrorMessages(), $serverParams),
                );
            }
        }

        return $this->renderView('registration/register', [
            'form' => $form,
            'data' => [
                'formSubmitUrl' => $this->url->generate('voyti/registration-register'),
                'loginUrl' => $this->url->generate('voyti/session-login'),
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

    /**
     * @return array<array-key, mixed>
     */
    private function parsedBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }
}
