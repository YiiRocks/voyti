<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stringable;
use YiiRocks\Voyti\Controller\RecoveryController;
use YiiRocks\Voyti\Controller\RegistrationController;
use YiiRocks\Voyti\Controller\SecurityController;
use YiiRocks\Voyti\Controller\SettingsController;
use YiiRocks\Voyti\Factory\MailFactory;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Form\Auth\ResendForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Voyti;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\Service\User\AccountConfirmationService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\ResendConfirmationService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use Yiisoft\Csrf\CsrfTokenMiddleware;
use Yiisoft\Csrf\MaskedCsrfToken;
use Yiisoft\Csrf\StubCsrfToken;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;
use Yiisoft\User\CurrentUser;

final class ControllerHarness
{
    public readonly Aliases $aliases;
    public readonly CurrentUser $currentUser;
    public readonly EmailChangeService $emailChangeService;
    public readonly EmailChangeStrategyFactory $emailChangeStrategyFactory;
    public readonly EventCaptureDispatcher $eventDispatcher;
    public readonly FakeSession $session;
    public readonly FakeUrlGenerator $url;
    public readonly HydratorInterface $hydrator;
    public readonly MailCapture $mailer;
    public readonly MailFactory $mailFactory;
    public readonly MailService $mailService;
    public readonly ModuleConfig $moduleConfig;
    public readonly PasswordHasher $passwordHasher;
    public readonly QrCodeUriGeneratorService $qrCodeUriGeneratorService;
    public readonly RegistrationController $registrationController;
    public readonly RecoveryController $recoveryController;
    public readonly ResetService $resetPasswordService;
    public readonly RegistrationForm $registrationFormPrototype;
    public readonly RecoveryForm $recoveryFormPrototype;
    public readonly ResendForm $resendFormPrototype;
    public readonly SecurityController $securityController;
    public readonly SettingsController $settingsController;
    public readonly TranslatorInterface $translator;
    public readonly UserProfileRepository $userProfiles;
    public readonly UserRepository $users;
    public readonly UserSocialAccountRepository $socialAccounts;
    public readonly UserTokenFactory $userTokenFactory;
    public readonly UserTokenRepository $userTokens;
    public readonly WebViewRenderer $webViewRenderer;
    public readonly RegisterService $userRegisterService;
    public readonly ConfirmationService $userConfirmationService;
    public readonly AccountConfirmationService $accountConfirmationService;
    public readonly ResendConfirmationService $resendConfirmationService;

    public function __construct(string $projectRoot)
    {
        $this->translator = new Translator('en', null, Voyti::TRANSLATION_CATEGORY);
        $this->translator->addCategorySources(
            new CategorySource(
                Voyti::TRANSLATION_CATEGORY,
                new MessageSource($projectRoot . '/src/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );

        $this->moduleConfig = new ModuleConfig(
            enableRegistration: true,
            enableEmailConfirmation: true,
            enableGdprCompliance: true,
            allowAccountDelete: true,
            allowPasswordRecovery: true,
            allowAdminPasswordRecovery: true,
            emailChangeStrategy: 1,
        );

        $this->aliases = new Aliases();

        $this->eventDispatcher = new EventCaptureDispatcher();
        $this->session = new FakeSession();
        $this->url = new FakeUrlGenerator();
        $this->hydrator = new Hydrator();
        $this->mailer = new MailCapture();
        $this->passwordHasher = new PasswordHasher();
        $this->userProfiles = new UserProfileRepository();
        $this->users = new UserRepository();
        $this->socialAccounts = new UserSocialAccountRepository();
        $this->userTokens = new UserTokenRepository();
        $this->userTokenFactory = new UserTokenFactory($this->userTokens);
        $this->mailService = new MailService(
            $this->mailer,
            $this->moduleConfig->mailPath,
            $this->moduleConfig->mailParams,
            $this->translator,
            $this->url,
        );
        $this->mailFactory = new MailFactory($this->mailService);
        $this->emailChangeStrategyFactory = new EmailChangeStrategyFactory($this->userTokenFactory, $this->mailFactory);
        $this->emailChangeService = new EmailChangeService($this->moduleConfig, $this->userTokens, $this->users);
        $this->qrCodeUriGeneratorService = new QrCodeUriGeneratorService();
        $this->currentUser = new CurrentUser(
            new IdentityRepository($this->users),
            $this->eventDispatcher,
        );
        $csrfToken = new MaskedCsrfToken(new StubCsrfToken('test-csrf-token'));
        $csrfMiddleware = new CsrfTokenMiddleware(new Psr17Factory(), $csrfToken);
        $this->webViewRenderer = new WebViewRenderer(
            responseFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            aliases: $this->aliases,
            view: new WebView($this->moduleConfig->viewPath),
            injections: [
                new \Yiisoft\Yii\View\Renderer\CsrfViewInjection($csrfToken, $csrfMiddleware),
            ],
        );
        $this->userRegisterService = new RegisterService(
            $this->users,
            $this->mailService,
            $this->eventDispatcher,
            $this->passwordHasher,
            $this->moduleConfig,
        );
        $this->userConfirmationService = new ConfirmationService($this->eventDispatcher, $this->userTokens);
        $this->accountConfirmationService = new AccountConfirmationService($this->userTokens);
        $this->resendConfirmationService = new ResendConfirmationService($this->userTokens, $this->mailService);
        $this->resetPasswordService = new ResetService(
            $this->passwordHasher,
            $this->moduleConfig,
            $this->eventDispatcher,
            $this->userTokens,
        );

        $this->registrationFormPrototype = new RegistrationForm($this->moduleConfig, $this->translator);
        $this->recoveryFormPrototype = new RecoveryForm($this->moduleConfig, $this->translator, RecoveryForm::SCENARIO_REQUEST);
        $this->resendFormPrototype = new ResendForm($this->moduleConfig, $this->translator);

        $this->registrationController = new RegistrationController(
            $this->translator,
            $this->webViewRenderer,
            $this->userRegisterService,
            $this->users,
            $this->userTokens,
            $this->userConfirmationService,
            $this->accountConfirmationService,
            $this->resendConfirmationService,
            $this->validator(),
            $this->eventDispatcher,
            $this->url,
            $this->moduleConfig,
            $this->hydrator,
        );

        $this->securityController = new SecurityController(
            $this->translator,
            $this->webViewRenderer,
            $this->users,
            $this->currentUser,
            $this->passwordHasher,
            $this->validator(),
            $this->eventDispatcher,
            $this->url,
            $this->session,
            $this->moduleConfig,
            new \YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService(
                $this->moduleConfig,
                $this->socialAccounts,
                $this->users,
                $this->currentUser,
                $this->session,
            ),
            new \YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService($this->socialAccounts),
            $this->socialAccounts,
            $this->hydrator,
        );

        $this->settingsController = new SettingsController(
            $this->translator,
            $this->webViewRenderer,
            $this->users,
            $this->userProfiles,
            $this->socialAccounts,
            $this->passwordHasher,
            $this->validator(),
            $this->eventDispatcher,
            $this->url,
            $this->moduleConfig,
            $this->emailChangeStrategyFactory,
            $this->qrCodeUriGeneratorService,
            $this->emailChangeService,
            $this->userTokens,
            $this->hydrator,
            $this->currentUser,
        );

        $this->recoveryController = new RecoveryController(
            $this->translator,
            $this->webViewRenderer,
            $this->url,
            $this->recoveryService(),
            $this->resetPasswordService,
            $this->users,
            $this->userTokens,
            $this->validator(),
            $this->eventDispatcher,
            $this->moduleConfig,
            $this->hydrator,
        );
    }

    public function request(string $method, array $parsedBody = [], array $queryParams = [], array $attributes = [], array $serverParams = []): ServerRequestInterface
    {
        $request = new \Nyholm\Psr7\ServerRequest($method, 'https://example.test/', [], null, '1.1', $serverParams);

        if ($parsedBody !== []) {
            $request = $request->withParsedBody($parsedBody);
        }
        if ($queryParams !== []) {
            $request = $request->withQueryParams($queryParams);
        }
        if ($attributes !== []) {
            foreach ($attributes as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }
        }

        return $request;
    }

    public function responseBody(ResponseInterface $response): string
    {
        $body = $response->getBody();
        $body->rewind();
        return $body->getContents();
    }

    public function formPayload(FormModel $form, array $data): array
    {
        return [$form->getFormName() => $data];
    }

    private function validator(): ValidatorInterface
    {
        return new class implements ValidatorInterface {
            #[\Override]
            public function validate(
                mixed $data,
                callable|iterable|object|string|null $rules = null,
                ?\Yiisoft\Validator\ValidationContext $context = null,
            ): Result
            {
                return new Result();
            }
        };
    }

    private function recoveryService(): RecoveryService
    {
        return new RecoveryService(
            $this->users,
            $this->mailService,
            $this->moduleConfig,
            $this->translator,
            $this->eventDispatcher,
        );
    }
}

final class EventCaptureDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $events = [];

    #[\Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        return $event;
    }

    /**
     * @return list<object>
     */
    public function events(): array
    {
        return $this->events;
    }
}

final class FakeSession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $values = [];
    private bool $active = true;
    private string $id = 'test-session';

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    #[\Override]
    public function close(): void
    {
        $this->active = false;
    }

    #[\Override]
    public function open(): void
    {
        $this->active = true;
    }

    #[\Override]
    public function isActive(): bool
    {
        return $this->active;
    }

    #[\Override]
    public function getId(): ?string
    {
        return $this->id;
    }

    #[\Override]
    public function setId(string $sessionId): void
    {
        $this->id = $sessionId;
    }

    #[\Override]
    public function regenerateId(): void
    {
        $this->id = 'test-session-' . uniqid('', true);
    }

    #[\Override]
    public function discard(): void
    {
        $this->values = [];
    }

    #[\Override]
    public function getName(): string
    {
        return 'TESTSESSID';
    }

    #[\Override]
    public function all(): array
    {
        return $this->values;
    }

    #[\Override]
    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    #[\Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    #[\Override]
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    #[\Override]
    public function clear(): void
    {
        $this->values = [];
    }

    #[\Override]
    public function destroy(): void
    {
        $this->values = [];
        $this->active = false;
    }

    #[\Override]
    public function getCookieParameters(): array
    {
        return [];
    }
}

final class FakeUrlGenerator implements \Yiisoft\Router\UrlGeneratorInterface
{
    #[\Override]
    public function generate(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null): string
    {
        return $this->format($name, $arguments, $queryParameters, $hash, false);
    }

    #[\Override]
    public function generateAbsolute(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null, ?string $scheme = null, ?string $host = null): string
    {
        return $this->format($name, $arguments, $queryParameters, $hash, true, $scheme, $host);
    }

    #[\Override]
    public function generateFromCurrent(array $replacedArguments = [], array $queryParameters = [], ?string $hash = null, ?string $fallbackRouteName = null): string
    {
        return $this->generate($fallbackRouteName ?? 'current', $replacedArguments, $queryParameters, $hash);
    }

    #[\Override]
    public function getUriPrefix(): string
    {
        return '';
    }

    #[\Override]
    public function setUriPrefix(string $name): void
    {
    }

    #[\Override]
    public function setDefaultArgument(string $name, Stringable|string|int|float|bool|null $value): void
    {
    }

    private function format(string $name, array $arguments, array $queryParameters, ?string $hash, bool $absolute, ?string $scheme = null, ?string $host = null): string
    {
        $path = '/' . ltrim($name, '/');
        if ($arguments !== []) {
            $path .= '/' . implode('/', array_map(static fn (mixed $value): string => (string) $value, $arguments));
        }
        if ($queryParameters !== []) {
            $path .= '?' . http_build_query($queryParameters);
        }
        if ($hash !== null) {
            $path .= '#' . $hash;
        }
        if (!$absolute) {
            return $path;
        }
        $scheme ??= 'https';
        $host ??= 'example.test';
        return $scheme . '://' . $host . $path;
    }
}

final class MailCapture implements MailerInterface
{
    /** @var list<MessageInterface> */
    private array $messages = [];

    #[\Override]
    public function send(MessageInterface $message): void
    {
        $this->messages[] = $message;
    }

    #[\Override]
    public function sendMultiple(array $messages): SendResults
    {
        foreach ($messages as $message) {
            $this->send($message);
        }

        return new SendResults($this->messages, []);
    }

    /**
     * @return list<MessageInterface>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}

final class IdentityRepository implements IdentityRepositoryInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    #[\Override]
    public function findIdentity(string $id): ?IdentityInterface
    {
        return $this->users->findById((int) $id);
    }
}
