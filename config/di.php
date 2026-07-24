<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use YiiRocks\Voyti\Adapter\IdentityAdapter;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\AuthClient\AuthClientRegistryFactory;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Http\ClientInterface;
use YiiRocks\Voyti\Http\Psr18Client;
use YiiRocks\Voyti\Listener;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\VoytiMiddleware;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\TwoFactor\BackupCodeService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Service\User\ApiTokenService;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\Service\UserSession\UserSessionDecorator;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\Cookies\CookieEncryptor;
use Yiisoft\Cookies\CookieMiddleware;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\Input\Http\HydratorAttributeParametersResolver;
use Yiisoft\Input\Http\RequestInputParametersResolver;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Middleware\Dispatcher\CompositeParametersResolver;
use Yiisoft\Middleware\Dispatcher\ParametersResolverInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\Db\AssignmentsStorage;
use Yiisoft\Rbac\Db\ItemsStorage;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

/** @var array $params */

/**
 * @throws LogicException if the "yiisoft/cookies" secretKey param is missing or empty.
 */
$cookieSecretKey = static function () use ($params): string {
    $secretKey = $params['yiisoft/cookies']['secretKey'] ?? null;
    if (!is_string($secretKey) || $secretKey === '') {
        throw new LogicException(
            'Missing "secretKey" in the "yiisoft/cookies" params. Configure '
            . '$params[\'yiisoft/cookies\'][\'secretKey\'] with a securely generated random string '
            . 'to encrypt the remember-me cookie.',
        );
    }

    return $secretKey;
};

return [
    // Module configuration, built once from the host's `yiirocks/voyti` params array.
    ModuleConfig::class => static fn() => new ModuleConfig(...($params['yiirocks/voyti'] ?? [])),

    // Default now() source; hosts with their own PSR-20 clock package can override this binding.
    ClockInterface::class => SystemClock::class,

    // Bridges satisfying vendor package contracts (yiisoft/auth, yiisoft/middleware-dispatcher).
    ParametersResolverInterface::class => fn(
        HydratorAttributeParametersResolver $hydratorResolver,
        RequestInputParametersResolver $requestInputResolver,
    ) => new CompositeParametersResolver($hydratorResolver, $requestInputResolver),
    IdentityRepositoryInterface::class => IdentityAdapter::class,
    IdentityWithTokenRepositoryInterface::class => IdentityAdapter::class,

    // PSR-15 middleware: VoytiMiddleware chains the remember-me and enforcement middleware.
    VoytiMiddleware::class => fn(
        RememberMeMiddleware $rememberMe,
        PasswordAgeEnforceMiddleware $passwordAge,
        SessionRevocationEnforceMiddleware $sessionRevocation,
        TwoFactorAuthenticationEnforceMiddleware $twoFactorAuth,
    ) => new VoytiMiddleware($rememberMe, $passwordAge, $sessionRevocation, $twoFactorAuth),

    // Cookie encryption middleware for remember-me cookies
    CookieEncryptor::class => static fn() => new CookieEncryptor($cookieSecretKey()),
    CookieSigner::class => static fn() => new CookieSigner($cookieSecretKey()),
    CookieMiddleware::class => fn(
        LoggerInterface $logger,
        CookieEncryptor $encryptor,
        CookieSigner $signer,
    ) => new CookieMiddleware(
        $logger,
        $encryptor,
        $signer,
        [
            'autoLogin' => CookieMiddleware::ENCRYPT,
        ],
    ),

    // Auditing.
    AuditLogService::class => AuditLogService::class,

    // RBAC: role/permission/rule administration and validation.
    // Default DB-backed storage, matching the tables this module's migration creates;
    // hosts with a different backend (e.g. rbac-php, a caching decorator) can override these.
    ItemsStorageInterface::class => ItemsStorage::class,
    AssignmentsStorageInterface::class => AssignmentsStorage::class,
    AuthHelper::class => fn(
        ManagerInterface $authManager,
        ItemsStorageInterface $itemsStorage,
        AssignmentsStorageInterface $assignmentsStorage,
        ModuleConfig $config,
        CurrentUser $currentUser,
    ) => new AuthHelper($authManager, $itemsStorage, $assignmentsStorage, $config, $currentUser),
    ItemsValidator::class => fn(
        ItemsStorageInterface $itemsStorage,
    ) => new ItemsValidator($itemsStorage),
    RuleValidator::class => new RuleValidator(),
    RuleEditionService::class => fn(
        ItemsStorageInterface $itemsStorage,
        RuleValidator $ruleValidator,
    ) => new RuleEditionService($itemsStorage, $ruleValidator),
    UpdateAssignmentsService::class => fn(
        ManagerInterface $authManager,
        AssignmentsStorageInterface $assignmentsStorage,
        ItemsValidator $itemsValidator,
    ) => new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator),

    // Admin dashboard: aggregates stats shown on the /admin/ landing page.
    DashboardService::class => DashboardService::class,

    // Passwords: generation, expiry, history and reset/recovery flows.
    PasswordGeneratorInterface::class => RandomPasswordGenerator::class,
    PasswordHistoryService::class => PasswordHistoryService::class,
    ExpireService::class => fn(
        ModuleConfig $config,
    ) => new ExpireService($config),
    RecoveryService::class => RecoveryService::class,
    ResetService::class => fn(
        ModuleConfig $config,
        EventDispatcherInterface $eventDispatcher,
        PasswordHistoryService $passwordHistoryService,
    ) => new ResetService($config, $eventDispatcher, $passwordHistoryService),

    // Registration, confirmation, and email-change lifecycle.
    MailService::class => fn(
        MailerInterface $mailer,
        ModuleConfig $config,
        TranslatorInterface $translator,
        UrlGeneratorInterface $url,
    ) => new MailService($mailer, $config->mailPath, $translator, $url, $config->appName),
    UserTokenFactory::class => UserTokenFactory::class,
    UserCreationHelper::class => UserCreationHelper::class,
    CreateService::class => CreateService::class,
    RegisterService::class => RegisterService::class,
    ConfirmationService::class => fn(
        EventDispatcherInterface $eventDispatcher,
        UserTokenFactory $userTokenFactory,
        MailService $mailService,
    ) => new ConfirmationService($eventDispatcher, $userTokenFactory, $mailService),
    EmailChangeService::class => fn(
        ModuleConfig $config,
        UserTokenFactory $tokenFactory,
        MailService $mailService,
    ) => new EmailChangeService($config, $tokenFactory, $mailService),
    BlockService::class => fn(
        EventDispatcherInterface $eventDispatcher,
        TerminateUserSessionsService $terminateUserSessionsService,
    ) => new BlockService($eventDispatcher, $terminateUserSessionsService),

    // Sessions and identity: login persistence, switching, API tokens, session tracking.
    RememberMeCookieService::class => static fn(
        ModuleConfig $config,
        ClockInterface $clock,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) => new RememberMeCookieService(
        $config->rememberLoginLifespan,
        clock: $clock,
        eventDispatcher: $eventDispatcher,
    ),
    SwitchIdentityService::class => fn(
        ModuleConfig $config,
        CurrentUser $currentUser,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher,
    ) => new SwitchIdentityService($config, $currentUser, $session, $eventDispatcher),
    ApiTokenService::class => ApiTokenService::class,
    UserSessionDecorator::class => fn(
        EventDispatcherInterface $eventDispatcher,
        ModuleConfig $config,
        ?SessionInterface $session = null,
    ) => new UserSessionDecorator($eventDispatcher, $config, $session),
    TerminateUserSessionsService::class => TerminateUserSessionsService::class,

    // Two-factor authentication: email codes, TOTP QR URIs, backup codes.
    EmailCodeGeneratorService::class => fn(
        MailService $mailService,
    ) => new EmailCodeGeneratorService($mailService),
    QrCodeUriGeneratorService::class => fn(
        ModuleConfig $config,
    ) => new QrCodeUriGeneratorService($config),
    BackupCodeService::class => fn() => new BackupCodeService(
        new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 6]),
    ),

    // Social auth: OAuth client registry and account linking/authentication.
    ClientInterface::class => static fn(
        PsrClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) => new Psr18Client($httpClient, $requestFactory, $streamFactory),
    AuthClientRegistryFactory::class => AuthClientRegistryFactory::class,
    AuthClientRegistry::class => fn(AuthClientRegistryFactory $factory) => $factory->create(),
    PendingSocialAccountService::class => PendingSocialAccountService::class,
    SocialAuthProviderService::class => SocialAuthProviderService::class,
    UserSocialAuthenticateService::class => fn(
        ModuleConfig $config,
        CurrentUser $currentUser,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher,
        UserCreationHelper $userCreationHelper,
        PendingSocialAccountService $pendingSocialAccountService,
    ) => new UserSocialAuthenticateService(
        $config,
        $currentUser,
        $session,
        $eventDispatcher,
        $userCreationHelper,
        $pendingSocialAccountService,
    ),

    // Event listeners bound to their concrete class for autowiring; wiring to events is in events.php.
    Listener\AdminNotificationListener::class => Listener\AdminNotificationListener::class,
    Listener\PasswordExpirationListener::class => Listener\PasswordExpirationListener::class,
    Listener\SessionListener::class => Listener\SessionListener::class,

    // Translation category source for this module's message files.
    'yiirocks/voyti.translator' => [
        'definition' => static fn() => new CategorySource(
            'voyti',
            new MessageSource(dirname(__DIR__) . '/resources/messages'),
            new SimpleMessageFormatter(),
        ),
        'tags' => ['translation.categorySource'],
    ],
];
