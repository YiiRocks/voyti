<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\GravatarHelper;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\IdentityService\CurrentUserIdentityService;
use YiiRocks\Voyti\IdentityServiceInterface;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;

return [
    ModuleConfig::class => ModuleConfig::class,

    IdentityServiceInterface::class => CurrentUserIdentityService::class,

    UserRepository::class => UserRepository::class,
    UserProfileRepository::class => UserProfileRepository::class,
    UserTokenRepository::class => UserTokenRepository::class,
    UserSocialAccountRepository::class => UserSocialAccountRepository::class,
    UserSessionHistoryRepository::class => UserSessionHistoryRepository::class,

    SecurityHelper::class => SecurityHelper::class,
    AuthHelper::class => fn (
        \Yiisoft\Rbac\ManagerInterface $authManager,
        ItemsStorageInterface $itemsStorage,
        AssignmentsStorageInterface $assignmentsStorage,
        ModuleConfig $config
    ) => new AuthHelper($authManager, $itemsStorage, $assignmentsStorage, $config),
    GravatarHelper::class => GravatarHelper::class,
    TimezoneHelper::class => TimezoneHelper::class,

    \YiiRocks\Voyti\Validator\Rbac\ItemsValidator::class => fn (
        ItemsStorageInterface $itemsStorage
    ) => new \YiiRocks\Voyti\Validator\Rbac\ItemsValidator($itemsStorage),
    \YiiRocks\Voyti\Validator\Rbac\RuleValidator::class => new \YiiRocks\Voyti\Validator\Rbac\RuleValidator,

    \YiiRocks\Voyti\Service\Rbac\ItemEditionService::class => fn (
        \Yiisoft\Rbac\ManagerInterface $authManager,
        ItemsStorageInterface $itemsStorage,
        \YiiRocks\Voyti\Validator\Rbac\ItemsValidator $itemsValidator
    ) => new \YiiRocks\Voyti\Service\Rbac\ItemEditionService($authManager, $itemsStorage, $itemsValidator),
    \YiiRocks\Voyti\Service\Rbac\RuleEditionService::class => fn (
        ItemsStorageInterface $itemsStorage,
        \YiiRocks\Voyti\Validator\Rbac\RuleValidator $ruleValidator
    ) => new \YiiRocks\Voyti\Service\Rbac\RuleEditionService($itemsStorage, $ruleValidator),
    \YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService::class => fn (
        \Yiisoft\Rbac\ManagerInterface $authManager,
        AssignmentsStorageInterface $assignmentsStorage,
        \YiiRocks\Voyti\Validator\Rbac\ItemsValidator $itemsValidator
    ) => new \YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator),
    \YiiRocks\Voyti\Service\MailService::class => fn (
        \Yiisoft\Mailer\MailerInterface $mailer,
        ModuleConfig $config,
        Aliases $aliases
    ) => new \YiiRocks\Voyti\Service\MailService($mailer, $config->mailParams, $aliases),
    \YiiRocks\Voyti\Service\User\AccountConfirmationService::class => fn (
        UserTokenRepository $tokenRepository
    ) => new \YiiRocks\Voyti\Service\User\AccountConfirmationService($tokenRepository),
    \YiiRocks\Voyti\Service\User\ResendConfirmationService::class => fn (
        UserTokenRepository $tokenRepository,
        \YiiRocks\Voyti\Service\MailService $mailService,
        SecurityHelper $securityHelper
    ) => new \YiiRocks\Voyti\Service\User\ResendConfirmationService($tokenRepository, $mailService, $securityHelper),
    \YiiRocks\Voyti\Service\SwitchIdentityService::class => fn (
        ModuleConfig $config,
        UserRepository $userRepository,
        IdentityServiceInterface $identityService,
        SessionInterface $session
    ) => new \YiiRocks\Voyti\Service\SwitchIdentityService($config, $userRepository, $identityService, $session),
    \YiiRocks\Voyti\Service\Password\ExpireService::class => fn (
        ModuleConfig $config
    ) => new \YiiRocks\Voyti\Service\Password\ExpireService($config),
    \YiiRocks\Voyti\Service\Password\RecoveryService::class => \YiiRocks\Voyti\Service\Password\RecoveryService::class,
    \YiiRocks\Voyti\Service\Password\ResetService::class => fn (
        SecurityHelper $securityHelper,
        ModuleConfig $config,
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher,
        UserTokenRepository $tokenRepository
    ) => new \YiiRocks\Voyti\Service\Password\ResetService($securityHelper, $config, $eventDispatcher, $tokenRepository),
    \YiiRocks\Voyti\Service\User\CreateService::class => \YiiRocks\Voyti\Service\User\CreateService::class,
    \YiiRocks\Voyti\Service\User\RegisterService::class => \YiiRocks\Voyti\Service\User\RegisterService::class,
    \YiiRocks\Voyti\Service\User\BlockService::class => fn (
        SecurityHelper $securityHelper,
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
    ) => new \YiiRocks\Voyti\Service\User\BlockService($securityHelper, $eventDispatcher),
    \YiiRocks\Voyti\Service\User\ConfirmationService::class => fn (
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher,
        UserTokenRepository $tokenRepository
    ) => new \YiiRocks\Voyti\Service\User\ConfirmationService($eventDispatcher, $tokenRepository),
    \YiiRocks\Voyti\Service\EmailChangeService::class => fn (
        ModuleConfig $config,
        UserTokenRepository $tokenRepository,
        UserRepository $userRepository
    ) => new \YiiRocks\Voyti\Service\EmailChangeService($config, $tokenRepository, $userRepository),
    \YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService::class => fn (
        SecurityHelper $securityHelper,
        \YiiRocks\Voyti\Service\MailService $mailService
    ) => new \YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService($securityHelper, $mailService),
    \YiiRocks\Voyti\Service\TwoFactor\SmsCodeGeneratorService::class => fn (
        SecurityHelper $securityHelper
    ) => new \YiiRocks\Voyti\Service\TwoFactor\SmsCodeGeneratorService($securityHelper),
    \YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService::class => fn (
        SecurityHelper $securityHelper
    ) => new \YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService($securityHelper),
    \YiiRocks\Voyti\Service\SessionHistory\SessionHistoryDecorator::class => fn (
        \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher,
        ModuleConfig $config,
        ?SessionInterface $session = null
    ) => new \YiiRocks\Voyti\Service\SessionHistory\SessionHistoryDecorator($eventDispatcher, $config, $session),

    \YiiRocks\Voyti\Service\Auth\SocialNetworkAuthenticateService::class => fn (
        ModuleConfig $config,
        UserSocialAccountRepository $socialNetworkAccountRepository,
        UserRepository $userRepository,
        IdentityServiceInterface $identityService,
        SessionInterface $session
    ) => new \YiiRocks\Voyti\Service\Auth\SocialNetworkAuthenticateService(
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
        'definition' => static fn () => new CategorySource(
            'voyti',
            new MessageSource(dirname(__DIR__) . '/src/resources/messages'),
            new SimpleMessageFormatter(),
        ),
        'tags' => ['translation.categorySource'],
    ],
];
