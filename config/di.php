<?php

declare(strict_types=1);

use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Auth\IdentityServiceInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\GravatarHelper;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\ProfileRepository;
use YiiRocks\Voyti\Repository\SocialNetworkAccountRepository;
use YiiRocks\Voyti\Repository\TokenRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\SessionHistoryRepository;

return [
    ModuleConfig::class => ModuleConfig::class,

    UserRepository::class => fn(ActiveRecordFactory $arFactory) => new UserRepository($arFactory),
    ProfileRepository::class => fn(ActiveRecordFactory $arFactory) => new ProfileRepository($arFactory),
    TokenRepository::class => TokenRepository::class,
    SocialNetworkAccountRepository::class => fn(ActiveRecordFactory $arFactory) => new SocialNetworkAccountRepository($arFactory),
    SessionHistoryRepository::class => fn(ActiveRecordFactory $arFactory) => new SessionHistoryRepository($arFactory),

    SecurityHelper::class => SecurityHelper::class,
    AuthHelper::class => fn(
        \Yiisoft\Rbac\ManagerInterface $authManager,
        ItemsStorageInterface $itemsStorage,
        AssignmentsStorageInterface $assignmentsStorage,
        ModuleConfig $config
    ) => new AuthHelper($authManager, $itemsStorage, $assignmentsStorage, $config),
    GravatarHelper::class => GravatarHelper::class,
    TimezoneHelper::class => TimezoneHelper::class,

    \YiiRocks\Voyti\Service\AuthItemEditionService::class => fn(
        \Yiisoft\Rbac\ManagerInterface $authManager,
        ItemsStorageInterface $itemsStorage
    ) => new \YiiRocks\Voyti\Service\AuthItemEditionService($authManager, $itemsStorage),
    \YiiRocks\Voyti\Service\AuthRuleEditionService::class => fn(
        ItemsStorageInterface $itemsStorage
    ) => new \YiiRocks\Voyti\Service\AuthRuleEditionService($itemsStorage),
    \YiiRocks\Voyti\Service\UpdateAuthAssignmentsService::class => fn(
        \Yiisoft\Rbac\ManagerInterface $authManager,
        AssignmentsStorageInterface $assignmentsStorage
    ) => new \YiiRocks\Voyti\Service\UpdateAuthAssignmentsService($authManager, $assignmentsStorage),
    \YiiRocks\Voyti\Service\MailService::class => fn(
        \Yiisoft\Mail\MailerInterface $mailer,
        ModuleConfig $config,
        Aliases $aliases
    ) => new \YiiRocks\Voyti\Service\MailService($mailer, $config->mailParams, $aliases),
    \YiiRocks\Voyti\Service\AccountConfirmationService::class => fn(
        TokenRepository $tokenRepository
    ) => new \YiiRocks\Voyti\Service\AccountConfirmationService($tokenRepository),
    \YiiRocks\Voyti\Service\ResendConfirmationService::class => fn(
        TokenRepository $tokenRepository,
        \YiiRocks\Voyti\Service\MailService $mailService,
        SecurityHelper $securityHelper
    ) => new \YiiRocks\Voyti\Service\ResendConfirmationService($tokenRepository, $mailService, $securityHelper),
    \YiiRocks\Voyti\Service\SwitchIdentityService::class => fn(
        ModuleConfig $config,
        UserRepository $userRepository,
        IdentityServiceInterface $identityService,
        SessionInterface $session
    ) => new \YiiRocks\Voyti\Service\SwitchIdentityService($config, $userRepository, $identityService, $session),
    \YiiRocks\Voyti\Service\PasswordExpireService::class => fn(
        ModuleConfig $config
    ) => new \YiiRocks\Voyti\Service\PasswordExpireService($config),
    \YiiRocks\Voyti\Service\PasswordRecoveryService::class => \YiiRocks\Voyti\Service\PasswordRecoveryService::class,
    \YiiRocks\Voyti\Service\ResetPasswordService::class => fn(
        SecurityHelper $securityHelper,
        ModuleConfig $config,
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher,
        TokenRepository $tokenRepository
    ) => new \YiiRocks\Voyti\Service\ResetPasswordService($securityHelper, $config, $eventDispatcher, $tokenRepository),
    \YiiRocks\Voyti\Service\UserCreateService::class => \YiiRocks\Voyti\Service\UserCreateService::class,
    \YiiRocks\Voyti\Service\UserRegisterService::class => \YiiRocks\Voyti\Service\UserRegisterService::class,
    \YiiRocks\Voyti\Service\UserBlockService::class => fn(
        SecurityHelper $securityHelper,
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
    ) => new \YiiRocks\Voyti\Service\UserBlockService($securityHelper, $eventDispatcher),
    \YiiRocks\Voyti\Service\UserConfirmationService::class => fn(
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher,
        TokenRepository $tokenRepository
    ) => new \YiiRocks\Voyti\Service\UserConfirmationService($eventDispatcher, $tokenRepository),
    \YiiRocks\Voyti\Service\EmailChangeService::class => fn(
        ModuleConfig $config,
        TokenRepository $tokenRepository,
        UserRepository $userRepository
    ) => new \YiiRocks\Voyti\Service\EmailChangeService($config, $tokenRepository, $userRepository),
    \YiiRocks\Voyti\Service\TwoFactorEmailCodeGeneratorService::class => fn(
        SecurityHelper $securityHelper,
        \YiiRocks\Voyti\Service\MailService $mailService
    ) => new \YiiRocks\Voyti\Service\TwoFactorEmailCodeGeneratorService($securityHelper, $mailService),
    \YiiRocks\Voyti\Service\TwoFactorSmsCodeGeneratorService::class => fn(
        SecurityHelper $securityHelper
    ) => new \YiiRocks\Voyti\Service\TwoFactorSmsCodeGeneratorService($securityHelper),
    \YiiRocks\Voyti\Service\TwoFactorQrCodeUriGeneratorService::class => fn(
        SecurityHelper $securityHelper
    ) => new \YiiRocks\Voyti\Service\TwoFactorQrCodeUriGeneratorService($securityHelper),
    \YiiRocks\Voyti\Service\SessionHistory\SessionHistoryDecorator::class => fn(
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher,
        ModuleConfig $config,
        SessionInterface $session
    ) => new \YiiRocks\Voyti\Service\SessionHistory\SessionHistoryDecorator($eventDispatcher, $config, $session),

    \YiiRocks\Voyti\Service\SocialNetworkAuthenticateService::class => fn(
        ModuleConfig $config,
        SocialNetworkAccountRepository $socialNetworkAccountRepository,
        UserRepository $userRepository,
        IdentityServiceInterface $identityService,
        SessionInterface $session
    ) => new \YiiRocks\Voyti\Service\SocialNetworkAuthenticateService(
        $config,
        $socialNetworkAccountRepository,
        $userRepository,
        $identityService,
        $session,
    ),

    \YiiRocks\Voyti\Factory\TokenFactory::class => \YiiRocks\Voyti\Factory\TokenFactory::class,
    \YiiRocks\Voyti\Factory\MailFactory::class => \YiiRocks\Voyti\Factory\MailFactory::class,
    \YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory::class => \YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory::class,

    'voyti.translator' => [
        'definition' => static fn (TranslatorInterface $translator) => $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(dirname(__DIR__) . '/src/resources/messages'),
                new SimpleMessageFormatter(),
            )
        ),
        'tags' => ['translator.category'],
    ],
];
