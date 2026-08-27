<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Session;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Auth\LoginChallengeInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Auth\BeforeLoginFormValidationEvent;
use YiiRocks\Voyti\Event\Auth\FailedLoginEvent;
use YiiRocks\Voyti\Event\Auth\LoginFormValidationFailedEvent;
use YiiRocks\Voyti\Event\Auth\LogoutEvent;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles login and logout. A successful password login can be interrupted by a
 * {@see LoginChallengeInterface} (collected via the `voyti.login-challenge` tag - e.g. a two-factor
 * step) before the session is established. The social-auth redirect/callback
 * flow itself is handled entirely by an extension package (its `AuthAction`/callback-service
 * wiring), reached via the `voyti/session-auth` route contributed by that package - core has no
 * compile-time knowledge of it.
 *
 * @psalm-suppress UndefinedClass Collection comes from yiisoft/yii-auth-client, a peer dependency
 * core has no compile-time knowledge of at all - $clientCollection only resolves non-null when
 * a social-auth extension package is installed (it binds Collection via its own config/di.php).
 */
final readonly class SessionController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private PasswordHasher $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
        private SessionInterface $session,
        private RememberMeCookieService $rememberMeCookieService,
        private VoytiConfig $config,
        private LoginCompletionService $loginCompletionService,
        /** @var iterable<LoginChallengeInterface> */
        private iterable $loginChallenges,
        private FormHydrator $formHydrator,
        private FlashNotifier $flashNotifier,
        private ?Collection $clientCollection = null,
    ) {}

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->currentUser->getIdentity() instanceof GuestIdentityInterface) {
            return $this->homeRedirectResponse();
        }

        $form = new LoginForm($this->config, $this->translator);
        $serverParams = $request->getServerParams();

        if ($this->formHydrator->populateFromPost($form, $request, map: ['rememberMe' => 'rememberMe'])) {
            $formData = $this->parsedBody($request);
            $this->eventDispatcher->dispatch(new BeforeLoginFormValidationEvent($formData, $serverParams));
            $validationResult = $this->formHydrator->validate($form);

            if ($validationResult->isValid()) {
                $user = User::findByUsernameOrEmail($form->login);

                try {
                    $this->loginCompletionService->checkBeforeLogin($user, $request);

                    if ($user === null) {
                        $form->addError(
                            $this->translator->translate('voyti.security.invalid_login', category: 'voyti'),
                            ['login'],
                        );
                        $this->eventDispatcher->dispatch(
                            new FailedLoginEvent($this->loginIdentifier($form), 'user_not_found', $serverParams),
                        );
                    } elseif (!$this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                        $form->addError(
                            $this->translator->translate('voyti.security.invalid_login', category: 'voyti'),
                            ['login'],
                        );
                        $this->eventDispatcher->dispatch(
                            new FailedLoginEvent($this->loginIdentifier($form), 'invalid_password', $serverParams),
                        );
                    } elseif ($user->isBlocked()) {
                        $form->addError(
                            $this->translator->translate('voyti.security.account_blocked', category: 'voyti'),
                            ['login'],
                        );
                        $this->eventDispatcher->dispatch(
                            new FailedLoginEvent($this->loginIdentifier($form), 'account_blocked', $serverParams),
                        );
                    } elseif ($this->config->enableEmailConfirmation && !$user->isConfirmed()) {
                        $form->addError(
                            $this->translator->translate('voyti.security.need_email_confirmation', category: 'voyti'),
                            ['login'],
                        );
                    } else {
                        foreach ($this->loginChallenges as $loginChallenge) {
                            $response = $loginChallenge->challenge($user, $form->rememberMe, $request);
                            if ($response !== null) {
                                return $response;
                            }
                        }

                        return $this->loginCompletionService->finalize($user, $form->rememberMe, $request);
                    }
                } catch (ActionPreventedException $exception) {
                    $form->addError($exception->getMessage(), $exception->getErrorDetails());
                    $this->eventDispatcher->dispatch(
                        new FailedLoginEvent($this->loginIdentifier($form), 'locked_out', $serverParams),
                    );
                }
            } else {
                $this->eventDispatcher->dispatch(
                    new LoginFormValidationFailedEvent($formData, $validationResult->getErrorMessages(), $serverParams),
                );
                $this->eventDispatcher->dispatch(
                    new FailedLoginEvent($this->loginIdentifier($form), 'validation_failed', $serverParams),
                );
            }
        }

        return $this->renderView('session/login', [
            'form' => $form,
            'data' => [
                'formSubmitUrl' => $this->url->generate('voyti/session-login'),
                'forgotPasswordUrl' => $this->url->generate('voyti/password-reset-request'),
                'showRegisterLink' => $this->config->enableRegistration,
                'registerUrl' => $this->url->generate('voyti/registration-register'),
                'recaptchaFieldHtml' => RecaptchaHelper::render($form, $this->config),
                'authChoice' => $this->buildAuthChoice(),
            ],
        ]);
    }

    public function logout(): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        $sessionId = $this->session->getId() ?? '';
        /** @infection-ignore-all Every non-guest identity is a User and every guest fails logout(), so the && vs || operator is unobservable. */
        if ($this->currentUser->logout() && $identity instanceof User) {
            $this->eventDispatcher->dispatch(new LogoutEvent($identity, $sessionId));

            if ($sessionId !== '') {
                $userId = $identity->getIdOrZero();
                $userSession = UserSessions::findByUserIdAndSessionId($userId, $sessionId);
                if ($userSession !== null) {
                    $userSession->setRevokedAt(time());
                    $userSession->save();
                    $this->eventDispatcher->dispatch(
                        new SessionEvent($userId, $sessionId, ['type' => SessionEvent::SESSION_TERMINATED]),
                    );
                }
            }

            $identity->setAuthKey(Random::string());
            $identity->setUpdatedAt(time());
            $identity->save();
        }

        return $this->rememberMeCookieService->expireCookie(
            $this->redirectWithFlash($this->config->getHomeUrl($this->url), 'voyti.security.logged_out'),
        );
    }

    /**
     * @psalm-suppress UndefinedClass AuthChoice comes from yiisoft/yii-auth-client - see the
     * class docblock's suppress comment for Collection.
     * @psalm-suppress MixedAssignment, MixedMethodCall, MixedReturnStatement Cascades from the
     * same undefined-class gap.
     */
    private function buildAuthChoice(): ?AuthChoice
    {
        if ($this->clientCollection === null) {
            return null;
        }

        // @codeCoverageIgnoreStart
        // $clientCollection only resolves non-null when a social-auth extension package is installed
        // (it binds Collection via its own config/di.php) - core's own test suite has no way to
        // exercise this branch, only that package's test suite (which constructs a real Collection) can.
        return AuthChoice::widget()
            ->authRoute('voyti/session-auth')
            ->linkAttributes(['class' => LinkButtonHelper::submitButtonClass()]);
        // @codeCoverageIgnoreEnd
    }

    private function homeRedirectResponse(): ResponseInterface
    {
        return $this->redirect($this->config->getHomeUrl($this->url));
    }

    private function loginIdentifier(LoginForm $form): ?string
    {
        return $form->login !== '' ? $form->login : null;
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
