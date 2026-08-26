<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use YiiRocks\Voyti\Adapter\IdentityAdapter;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Controller\Registration\RegistrationController;
use YiiRocks\Voyti\Controller\Session\SessionController;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Listener;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\VoytiMiddleware;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
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
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\Service\UserSession\UserSessionDecorator;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Cookies\CookieEncryptor;
use Yiisoft\Cookies\CookieMiddleware;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\Definitions\Reference;
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
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\View\View;

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
    VoytiConfig::class => static fn() => new VoytiConfig(
        // General
        appName: $params['yiirocks/voyti']['appName'] ?? 'Voyti',
        homeRoute: $params['yiirocks/voyti']['homeRoute'] ?? 'home',
        // Authentication & Registration
        enableRegistration: $params['yiirocks/voyti']['enableRegistration'] ?? true,
        enableEmailConfirmation: $params['yiirocks/voyti']['enableEmailConfirmation'] ?? true,
        allowPasswordRecovery: $params['yiirocks/voyti']['allowPasswordRecovery'] ?? true,
        allowAdminPasswordRecovery: $params['yiirocks/voyti']['allowAdminPasswordRecovery'] ?? false,
        allowAccountDelete: $params['yiirocks/voyti']['allowAccountDelete'] ?? false,
        emailChangeConfirmation: $params['yiirocks/voyti']['emailChangeConfirmation'] ?? EmailChangeConfirmation::NEW,
        rememberLoginLifespan: $params['yiirocks/voyti']['rememberLoginLifespan'] ?? 2592000,
        tokenConfirmationLifespan: $params['yiirocks/voyti']['tokenConfirmationLifespan'] ?? 86400,
        tokenRecoveryLifespan: $params['yiirocks/voyti']['tokenRecoveryLifespan'] ?? 21600,
        enableSwitchIdentities: $params['yiirocks/voyti']['enableSwitchIdentities'] ?? true,
        mailAdminOnRegister: $params['yiirocks/voyti']['mailAdminOnRegister'] ?? null,
        recaptchaVersion: $params['yiirocks/voyti']['recaptchaVersion'] ?? RecaptchaVersion::V3,
        // Session & Security
        maxPasswordAge: $params['yiirocks/voyti']['maxPasswordAge'] ?? 0,
        enablePasswordComplexity: $params['yiirocks/voyti']['enablePasswordComplexity'] ?? false,
        passwordHistoryLimit: $params['yiirocks/voyti']['passwordHistoryLimit'] ?? 10,
        administratorPermissionName: $params['yiirocks/voyti']['administratorPermissionName'] ?? 'voyti-admin',
        profileVisibility: $params['yiirocks/voyti']['profileVisibility'] ?? ProfileVisibility::USERS,
        enableAuditLog: $params['yiirocks/voyti']['enableAuditLog'] ?? true,
        rememberMeCookieDomain: $params['yiirocks/voyti']['rememberMeCookieDomain'] ?? null,
        // Views & Mail
        viewPath: $params['yiirocks/voyti']['viewPath'] ?? null,
        mailPath: $params['yiirocks/voyti']['mailPath'] ?? VoytiConfig::DEFAULT_MAIL_PATH,
        // Admin Dashboard
        enableRecommendations: $params['yiirocks/voyti']['enableRecommendations'] ?? true,
        // Contributed by other packages - not meant to be set by host apps directly
        accountMenuItems: $params['yiirocks/voyti']['accountMenuItems'] ?? [],
        privacyMenuItems: $params['yiirocks/voyti']['privacyMenuItems'] ?? [],
        viewsPackagePaths: $params['yiirocks/voyti']['viewsPackagePaths'] ?? [],
    ),

    // Default now() source; hosts with their own PSR-20 clock package can override this binding.
    ClockInterface::class => SystemClock::class,

    // Bridges satisfying vendor package contracts (yiisoft/auth, yiisoft/middleware-dispatcher).
    ParametersResolverInterface::class => fn(
        HydratorAttributeParametersResolver $hydratorResolver,
        RequestInputParametersResolver $requestInputResolver,
    ) => new CompositeParametersResolver($hydratorResolver, $requestInputResolver),
    IdentityRepositoryInterface::class => IdentityAdapter::class,

    // PSR-15 middleware: VoytiMiddleware chains remember-me plus every enforcement middleware tagged
    // `voyti.enforce-middleware`. Core tags session-revocation and password-age (below); packages
    // such as yiirocks/voyti-2fa tag their own, joining the chain with no host wiring.
    SessionRevocationEnforceMiddleware::class => [
        'class' => SessionRevocationEnforceMiddleware::class,
        'tags' => ['voyti.enforce-middleware'],
    ],
    PasswordAgeEnforceMiddleware::class => [
        'class' => PasswordAgeEnforceMiddleware::class,
        'tags' => ['voyti.enforce-middleware'],
    ],
    VoytiMiddleware::class => [
        'class' => VoytiMiddleware::class,
        '__construct()' => [
            'rememberMe' => Reference::to(RememberMeMiddleware::class),
            'enforcementMiddlewares' => Reference::to('tag@voyti.enforce-middleware'),
        ],
    ],

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
        VoytiConfig $config,
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
        VoytiConfig $config,
    ) => new ExpireService($config),
    RecoveryService::class => RecoveryService::class,
    ResetService::class => fn(
        VoytiConfig $config,
        EventDispatcherInterface $eventDispatcher,
        PasswordHistoryService $passwordHistoryService,
    ) => new ResetService($config, $eventDispatcher, $passwordHistoryService),

    // Registration, confirmation, and email-change lifecycle.
    MailService::class => fn(
        MailerInterface $mailer,
        VoytiConfig $config,
        TranslatorInterface $translator,
        UrlGeneratorInterface $url,
        View $view,
    ) => new MailService($mailer, $config->mailPath, $view, $translator, $url, $config->appName),
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
        VoytiConfig $config,
        UserTokenFactory $tokenFactory,
        MailService $mailService,
    ) => new EmailChangeService($config, $tokenFactory, $mailService),
    BlockService::class => fn(
        EventDispatcherInterface $eventDispatcher,
        TerminateUserSessionsService $terminateUserSessionsService,
    ) => new BlockService($eventDispatcher, $terminateUserSessionsService),

    // Sessions and identity: login persistence, switching, API tokens, session tracking.
    RememberMeCookieService::class => static fn(
        VoytiConfig $config,
        ClockInterface $clock,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) => new RememberMeCookieService(
        $config->rememberLoginLifespan,
        clock: $clock,
        eventDispatcher: $eventDispatcher,
        cookieDomain: $config->rememberMeCookieDomain,
    ),
    SwitchIdentityService::class => fn(
        VoytiConfig $config,
        CurrentUser $currentUser,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher,
    ) => new SwitchIdentityService($config, $currentUser, $session, $eventDispatcher),
    UserSessionDecorator::class => fn(
        EventDispatcherInterface $eventDispatcher,
        VoytiConfig $config,
        ?SessionInterface $session = null,
    ) => new UserSessionDecorator($eventDispatcher, $config, $session),
    TerminateUserSessionsService::class => TerminateUserSessionsService::class,

    // Login challenges: steps that may interrupt a password login before the session is established
    // (e.g. two-factor from yiirocks/voyti-2fa). Packages tag their challenge with
    // `voyti.login-challenge`; SessionController consults them all, in registration order.
    SessionController::class => [
        'class' => SessionController::class,
        '__construct()' => [
            'loginChallenges' => Reference::to('tag@voyti.login-challenge'),
        ],
    ],

    // Post-registration hooks: side effects run against a newly registered user (e.g. connecting a
    // pending social account from yiirocks/voyti-social-auth). Packages tag their hook with
    // `voyti.post-registration-hook`; RegistrationController consults them all, in registration order.
    RegistrationController::class => [
        'class' => RegistrationController::class,
        '__construct()' => [
            'postRegistrationHooks' => Reference::to('tag@voyti.post-registration-hook'),
        ],
    ],

    // Post-login hooks: side effects run against a user whose login has just completed (e.g.
    // connecting a pending social account from yiirocks/voyti-social-auth). Packages tag their hook
    // with `voyti.post-login-hook`; LoginCompletionService consults them all, in registration order.
    LoginCompletionService::class => [
        'class' => LoginCompletionService::class,
        '__construct()' => [
            'postLoginHooks' => Reference::to('tag@voyti.post-login-hook'),
        ],
    ],

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
