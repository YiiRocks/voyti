<?php

declare(strict_types=1);

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\AuthClient\AuthClientRegistryFactory;
use YiiRocks\Voyti\Factory\MailFactory;
use YiiRocks\Voyti\Factory\TokenFactory;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\GravatarHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Http\ClientInterface;
use YiiRocks\Voyti\Http\Psr18Client;
use YiiRocks\Voyti\Listener;
use YiiRocks\Voyti\Middleware\RouteParametersResolver;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\SmsCodeGeneratorService;
use YiiRocks\Voyti\Service\User\AccountConfirmationService;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\ResendConfirmationService;
use YiiRocks\Voyti\Service\UserSessionHistory\UserSessionHistoryDecorator;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Middleware\Dispatcher\ParametersResolverInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
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

return [
    ParametersResolverInterface::class => RouteParametersResolver::class,

    ModuleConfig::class => static fn () => ModuleConfig::fromArray($params['yiirocks/voyti'] ?? []),
    RememberMeCookieService::class => static fn (ModuleConfig $config) => new RememberMeCookieService(
        $config->rememberLoginLifespan,
    ),

    UserRepository::class => UserRepository::class,
    UserProfileRepository::class => UserProfileRepository::class,
    UserTokenRepository::class => UserTokenRepository::class,
    UserSocialAccountRepository::class => UserSocialAccountRepository::class,
    UserSessionHistoryRepository::class => UserSessionHistoryRepository::class,

    AuthHelper::class => fn (
        ManagerInterface $authManager,
        ItemsStorageInterface $itemsStorage,
        AssignmentsStorageInterface $assignmentsStorage,
        ModuleConfig $config,
        CurrentUser $currentUser,
    ) => new AuthHelper($authManager, $itemsStorage, $assignmentsStorage, $config, $currentUser),
    GravatarHelper::class => GravatarHelper::class,
    TimezoneHelper::class => TimezoneHelper::class,
    ClientInterface::class => static fn (
        PsrClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) => new Psr18Client($httpClient, $requestFactory, $streamFactory),
    AuthClientRegistryFactory::class => AuthClientRegistryFactory::class,
    AuthClientRegistry::class => fn (AuthClientRegistryFactory $factory) => $factory->create(),

    ItemsValidator::class => fn (
        ItemsStorageInterface $itemsStorage
    ) => new ItemsValidator($itemsStorage),
    RuleValidator::class => new RuleValidator,

    RuleEditionService::class => fn (
        ItemsStorageInterface $itemsStorage,
        RuleValidator $ruleValidator
    ) => new RuleEditionService($itemsStorage, $ruleValidator),
    UpdateAssignmentsService::class => fn (
        ManagerInterface $authManager,
        AssignmentsStorageInterface $assignmentsStorage,
        ItemsValidator $itemsValidator
    ) => new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator),
    MailService::class => fn (
        MailerInterface $mailer,
        ModuleConfig $config,
        TranslatorInterface $translator,
        UrlGeneratorInterface $url
    ) => new MailService($mailer, $config->mailPath, $config->mailParams, $translator, $url, $config->appName),
    AccountConfirmationService::class => fn (
        UserTokenRepository $tokenRepository
    ) => new AccountConfirmationService($tokenRepository),
    ResendConfirmationService::class => fn (
        UserTokenRepository $tokenRepository,
        MailService $mailService,
    ) => new ResendConfirmationService($tokenRepository, $mailService),
    SwitchIdentityService::class => fn (
        ModuleConfig $config,
        UserRepository $userRepository,
        CurrentUser $currentUser,
        SessionInterface $session
    ) => new SwitchIdentityService($config, $userRepository, $currentUser, $session),
    ExpireService::class => fn (
        ModuleConfig $config
    ) => new ExpireService($config),
    RecoveryService::class => RecoveryService::class,
    ResetService::class => fn (
        PasswordHasher $passwordHasher,
        ModuleConfig $config,
        EventDispatcherInterface $eventDispatcher,
        UserTokenRepository $tokenRepository
    ) => new ResetService($passwordHasher, $config, $eventDispatcher, $tokenRepository),
    CreateService::class => CreateService::class,
    RegisterService::class => RegisterService::class,
    BlockService::class => fn (
        EventDispatcherInterface $eventDispatcher
    ) => new BlockService($eventDispatcher),
    ConfirmationService::class => fn (
        EventDispatcherInterface $eventDispatcher,
        UserTokenRepository $tokenRepository
    ) => new ConfirmationService($eventDispatcher, $tokenRepository),
    EmailChangeService::class => fn (
        ModuleConfig $config,
        UserTokenRepository $tokenRepository,
        UserRepository $userRepository
    ) => new EmailChangeService($config, $tokenRepository, $userRepository),
    EmailCodeGeneratorService::class => fn (
        MailService $mailService
    ) => new EmailCodeGeneratorService($mailService),
    SmsCodeGeneratorService::class => fn (
    ) => new SmsCodeGeneratorService,
    QrCodeUriGeneratorService::class => fn (
        ModuleConfig $config
    ) => new QrCodeUriGeneratorService($config),
    UserSessionHistoryDecorator::class => fn (
        EventDispatcherInterface $eventDispatcher,
        ModuleConfig $config,
        ?SessionInterface $session = null
    ) => new UserSessionHistoryDecorator($eventDispatcher, $config, $session),
    PendingSocialAccountService::class => PendingSocialAccountService::class,
    SocialAuthProviderService::class => SocialAuthProviderService::class,

    UserSocialAuthenticateService::class => fn (
        ModuleConfig $config,
        UserSocialAccountRepository $socialNetworkAccountRepository,
        UserRepository $userRepository,
        CurrentUser $currentUser,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher,
    ) => new UserSocialAuthenticateService(
        $config,
        $socialNetworkAccountRepository,
        $userRepository,
        $currentUser,
        $session,
        $eventDispatcher,
    ),

    TokenFactory::class => TokenFactory::class,
    MailFactory::class => MailFactory::class,
    EmailChangeStrategyFactory::class => EmailChangeStrategyFactory::class,

    Listener\AdminNotificationListener::class => Listener\AdminNotificationListener::class,
    Listener\MailChangeConfirmationListener::class => Listener\MailChangeConfirmationListener::class,
    Listener\PasswordExpirationListener::class => Listener\PasswordExpirationListener::class,
    Listener\SessionHistoryListener::class => Listener\SessionHistoryListener::class,

    'yiirocks/voyti.translator' => [
        'definition' => static fn () => new CategorySource(
            'voyti',
            new MessageSource(dirname(__DIR__) . '/src/resources/messages'),
            new SimpleMessageFormatter(),
        ),
        'tags' => ['translation.categorySource'],
    ],
];
