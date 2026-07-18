<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Psr\Http\Message\ResponseFactoryInterface;
use YiiRocks\Voyti\Adapter\IdentityAdapter;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Controller\Account\AccountController;
use YiiRocks\Voyti\Controller\Account\SessionController as AccountSessionController;
use YiiRocks\Voyti\Controller\Admin\AuditLog\AuditLogController;
use YiiRocks\Voyti\Controller\Admin\Dashboard\DashboardController;
use YiiRocks\Voyti\Controller\Admin\Rbac\RbacController;
use YiiRocks\Voyti\Controller\Admin\Rbac\Rule\RuleController;
use YiiRocks\Voyti\Controller\Admin\User\UserController;
use YiiRocks\Voyti\Controller\PasswordReset\PasswordResetController;
use YiiRocks\Voyti\Controller\Privacy\PrivacyController;
use YiiRocks\Voyti\Controller\Profile\ProfileController;
use YiiRocks\Voyti\Controller\Registration\RegistrationController;
use YiiRocks\Voyti\Controller\Session\SessionController;
use YiiRocks\Voyti\Controller\SocialNetwork\SocialNetworkController;
use YiiRocks\Voyti\Controller\TwoFactor\TwoFactorController;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
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
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class ControllerHarness
{
    private AssignmentsStorageInterface $assignmentsStorage;
    private AuthClientRegistry $authClientRegistry;
    private ManagerInterface $authManager;
    private EventCaptureDispatcher $eventDispatcher;
    private ItemsStorageInterface $itemsStorage;
    private MailCapture $mailer;
    private FakeSession $session;
    private FakeUrlGenerator $url;

    public function __construct(
        private ModuleConfig $config,
        ?ItemsStorageInterface $itemsStorage = null,
        ?AssignmentsStorageInterface $assignmentsStorage = null,
    ) {
        $this->itemsStorage = $itemsStorage ?? new SimpleItemsStorage();
        $this->assignmentsStorage = $assignmentsStorage ?? new SimpleAssignmentsStorage();
        $this->authManager = new Manager($this->itemsStorage, $this->assignmentsStorage);
        $this->eventDispatcher = new EventCaptureDispatcher();
        $this->session = new FakeSession();
        $this->url = new FakeUrlGenerator();
        $this->mailer = new MailCapture();
        $this->authClientRegistry = new AuthClientRegistry();
    }

    public function createAccountController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        HydratorInterface $hydrator,
        FlashInterface $flash,
        ?PasswordHasher $passwordHasher = null,
        ?EmailChangeService $emailChangeService = null,
        ?PasswordHistoryService $passwordHistoryService = null,
    ): AccountController {
        $passwordHasher ??= new PasswordHasher();
        $emailChangeService ??= new EmailChangeService(
            $this->config,
            new UserTokenFactory(),
            new MailService(
                $this->mailer,
                $this->config->mailPath,
                $translator,
                $this->url,
                $this->config->appName,
            ),
        );
        $passwordHistoryService ??= new PasswordHistoryService($passwordHasher, $this->config);

        return new AccountController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            validator: $validator,
            url: $this->url,
            config: $this->config,
            emailChangeService: $emailChangeService,
            hydrator: $hydrator,
            currentUser: $currentUser,
            responseFactory: $responseFactory,
            flash: $flash,
            passwordHistoryService: $passwordHistoryService,
        );
    }

    public function createAccountSessionController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        FlashInterface $flash,
    ): AccountSessionController {
        return new AccountSessionController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            currentUser: $currentUser,
            responseFactory: $responseFactory,
            url: $this->url,
            session: $this->session,
            config: $this->config,
            eventDispatcher: $this->eventDispatcher,
            flash: $flash,
        );
    }

    public function createAuditLogController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ResponseFactoryInterface $responseFactory,
        FlashInterface $flash,
    ): AuditLogController {
        return new AuditLogController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            url: $this->url,
            responseFactory: $responseFactory,
            config: $this->config,
            flash: $flash,
        );
    }

    public function createDashboardController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        CurrentUser $currentUser,
        FlashInterface $flash,
        ?DashboardService $dashboardService = null,
    ): DashboardController {
        $dashboardService ??= new DashboardService(
            $this->createAuthHelper($currentUser),
            $this->config,
            $this->itemsStorage,
            $translator,
        );

        return new DashboardController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            url: $this->url,
            config: $this->config,
            dashboardService: $dashboardService,
            flash: $flash,
        );
    }

    public function createPasswordResetController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        ResponseFactoryInterface $responseFactory,
        HydratorInterface $hydrator,
        FlashInterface $flash,
        ?RecoveryService $recoveryService = null,
        ?ResetService $resetService = null,
    ): PasswordResetController {
        $recoveryService ??= new RecoveryService();
        $passwordHasher = new PasswordHasher();
        $resetService ??= new ResetService(
            $this->config,
            $this->eventDispatcher,
            new PasswordHistoryService($passwordHasher, $this->config),
        );

        return new PasswordResetController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            url: $this->url,
            passwordRecoveryService: $recoveryService,
            resetPasswordService: $resetService,
            validator: $validator,
            eventDispatcher: $this->eventDispatcher,
            config: $this->config,
            hydrator: $hydrator,
            responseFactory: $responseFactory,
            flash: $flash,
        );
    }

    public function createPrivacyController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        HydratorInterface $hydrator,
        FlashInterface $flash,
        ?PasswordHasher $passwordHasher = null,
        ?TerminateUserSessionsService $terminateUserSessionsService = null,
    ): PrivacyController {
        $passwordHasher ??= new PasswordHasher();
        $terminateUserSessionsService ??= $this->createTerminateUserSessionsService();

        return new PrivacyController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            passwordHasher: $passwordHasher,
            validator: $validator,
            eventDispatcher: $this->eventDispatcher,
            url: $this->url,
            config: $this->config,
            hydrator: $hydrator,
            currentUser: $currentUser,
            responseFactory: $responseFactory,
            terminateUserSessionsService: $terminateUserSessionsService,
            flash: $flash,
        );
    }

    public function createProfileController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        HydratorInterface $hydrator,
        FlashInterface $flash,
        ?AuthHelper $authHelper = null,
        ?SwitchIdentityService $switchIdentityService = null,
    ): ProfileController {
        $authHelper ??= $this->createAuthHelper($currentUser);
        $switchIdentityService ??= new SwitchIdentityService(
            $this->config,
            $currentUser,
            $this->session,
            $this->eventDispatcher,
        );

        return new ProfileController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            validator: $validator,
            url: $this->url,
            authHelper: $authHelper,
            config: $this->config,
            currentUser: $currentUser,
            eventDispatcher: $this->eventDispatcher,
            hydrator: $hydrator,
            responseFactory: $responseFactory,
            flash: $flash,
            switchIdentityService: $switchIdentityService,
        );
    }

    public function createRbacController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        ResponseFactoryInterface $responseFactory,
        FlashInterface $flash,
        ?CurrentUser $currentUser = null,
        ?AuditLogService $auditLogService = null,
    ): RbacController {
        $currentUser ??= new CurrentUser(new IdentityAdapter($this->config), $this->eventDispatcher);
        $auditLogService ??= new AuditLogService($this->config);

        return new RbacController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            url: $this->url,
            validator: $validator,
            responseFactory: $responseFactory,
            itemsStorage: $this->itemsStorage,
            managerInterface: $this->authManager,
            assignmentsStorage: $this->assignmentsStorage,
            flash: $flash,
            config: $this->config,
            auditLogService: $auditLogService,
            currentUser: $currentUser,
        );
    }

    public function createRegistrationController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        ResponseFactoryInterface $responseFactory,
        HydratorInterface $hydrator,
        FlashInterface $flash,
        ?RegisterService $registerService = null,
        ?ConfirmationService $confirmationService = null,
        ?PendingSocialAccountService $pendingSocialAccountService = null,
    ): RegistrationController {
        $registerService ??= new RegisterService();
        $mailService = new MailService(
            $this->mailer,
            $this->config->mailPath,
            $translator,
            $this->url,
            $this->config->appName,
        );
        $confirmationService ??= new ConfirmationService(
            $this->eventDispatcher,
            new UserTokenFactory(),
            $mailService,
        );
        $pendingSocialAccountService ??= new PendingSocialAccountService();

        return new RegistrationController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            userRegisterService: $registerService,
            confirmationService: $confirmationService,
            validator: $validator,
            url: $this->url,
            config: $this->config,
            pendingSocialAccountService: $pendingSocialAccountService,
            hydrator: $hydrator,
            responseFactory: $responseFactory,
            flash: $flash,
            authClientRegistry: $this->authClientRegistry,
        );
    }

    public function createRuleController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        ResponseFactoryInterface $responseFactory,
        FlashInterface $flash,
        ?AuthHelper $authHelper = null,
        ?RuleEditionService $ruleEditionService = null,
        ?CurrentUser $currentUser = null,
        ?AuditLogService $auditLogService = null,
    ): RuleController {
        $currentUser ??= new CurrentUser(new IdentityAdapter($this->config), $this->eventDispatcher);
        $authHelper ??= $this->createAuthHelper($currentUser);
        $ruleEditionService ??= new RuleEditionService(
            $this->itemsStorage,
            new RuleValidator(),
        );
        $auditLogService ??= new AuditLogService($this->config);

        return new RuleController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            authHelper: $authHelper,
            url: $this->url,
            validator: $validator,
            authRuleEditionService: $ruleEditionService,
            responseFactory: $responseFactory,
            flash: $flash,
            config: $this->config,
            auditLogService: $auditLogService,
            currentUser: $currentUser,
        );
    }

    public function createSessionController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        HydratorInterface $hydrator,
        FlashInterface $flash,
        ?PasswordHasher $passwordHasher = null,
        ?RememberMeCookieService $rememberMeCookieService = null,
        ?SocialAuthProviderService $socialAuthProviderService = null,
        ?PendingSocialAccountService $pendingSocialAccountService = null,
        ?UserSocialAuthenticateService $socialNetworkAuthenticateService = null,
        ?UserSocialAccountConnectService $socialNetworkAccountConnectService = null,
        ?EmailCodeGeneratorService $twoFactorEmailCodeService = null,
        ?BackupCodeService $backupCodeService = null,
    ): SessionController {
        $passwordHasher ??= new PasswordHasher();
        $rememberMeCookieService ??= new RememberMeCookieService(
            $this->config->rememberLoginLifespan,
            eventDispatcher: $this->eventDispatcher,
        );
        $socialAuthProviderService ??= new SocialAuthProviderService();
        $pendingSocialAccountService ??= new PendingSocialAccountService();
        $socialNetworkAuthenticateService ??= new UserSocialAuthenticateService(
            $this->config,
            $currentUser,
            $this->session,
            $this->eventDispatcher,
            new UserCreationHelper(
                new MailService(
                    $this->mailer,
                    $this->config->mailPath,
                    $translator,
                    $this->url,
                    $this->config->appName,
                ),
                $this->eventDispatcher,
                $passwordHasher,
                $this->config,
                new PasswordHistoryService($passwordHasher, $this->config),
            ),
        );
        $socialNetworkAccountConnectService ??= new UserSocialAccountConnectService();
        $twoFactorEmailCodeService ??= new EmailCodeGeneratorService(
            new MailService(
                $this->mailer,
                $this->config->mailPath,
                $translator,
                $this->url,
                $this->config->appName,
            ),
        );
        $backupCodeService ??= new BackupCodeService($passwordHasher);

        return new SessionController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            currentUser: $currentUser,
            passwordHasher: $passwordHasher,
            validator: $validator,
            eventDispatcher: $this->eventDispatcher,
            responseFactory: $responseFactory,
            url: $this->url,
            session: $this->session,
            rememberMeCookieService: $rememberMeCookieService,
            config: $this->config,
            authClientRegistry: $this->authClientRegistry,
            socialAuthProviderService: $socialAuthProviderService,
            pendingSocialAccountService: $pendingSocialAccountService,
            socialNetworkAuthenticateService: $socialNetworkAuthenticateService,
            socialNetworkAccountConnectService: $socialNetworkAccountConnectService,
            hydrator: $hydrator,
            twoFactorEmailCodeService: $twoFactorEmailCodeService,
            flash: $flash,
            backupCodeService: $backupCodeService,
        );
    }

    public function createSocialNetworkController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        FlashInterface $flash,
    ): SocialNetworkController {
        return new SocialNetworkController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            url: $this->url,
            config: $this->config,
            authClientRegistry: $this->authClientRegistry,
            currentUser: $currentUser,
            responseFactory: $responseFactory,
            flash: $flash,
        );
    }

    public function createTwoFactorController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        FlashInterface $flash,
        ?QrCodeUriGeneratorService $twoFactorQrCodeService = null,
        ?EmailCodeGeneratorService $twoFactorEmailCodeService = null,
        ?BackupCodeService $backupCodeService = null,
    ): TwoFactorController {
        $twoFactorQrCodeService ??= new QrCodeUriGeneratorService($this->config);
        $twoFactorEmailCodeService ??= new EmailCodeGeneratorService(
            new MailService(
                $this->mailer,
                $this->config->mailPath,
                $translator,
                $this->url,
                $this->config->appName,
            ),
        );
        $backupCodeService ??= new BackupCodeService(new PasswordHasher());

        return new TwoFactorController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            url: $this->url,
            config: $this->config,
            currentUser: $currentUser,
            responseFactory: $responseFactory,
            twoFactorQrCodeService: $twoFactorQrCodeService,
            twoFactorEmailCodeService: $twoFactorEmailCodeService,
            flash: $flash,
            backupCodeService: $backupCodeService,
        );
    }

    public function createUserController(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        HydratorInterface $hydrator,
        FlashInterface $flash,
        ?PasswordGeneratorInterface $passwordGenerator = null,
        ?CreateService $createService = null,
        ?BlockService $blockService = null,
        ?ConfirmationService $confirmationService = null,
        ?RecoveryService $recoveryService = null,
        ?ExpireService $expireService = null,
        ?SwitchIdentityService $switchIdentityService = null,
        ?UpdateAssignmentsService $updateAssignmentsService = null,
        ?AuthHelper $authHelper = null,
        ?PasswordHistoryService $passwordHistoryService = null,
        ?AuditLogService $auditLogService = null,
    ): UserController {
        $passwordHasher = new PasswordHasher();
        $passwordGenerator ??= $this->createPasswordGenerator();
        $passwordHistoryService ??= new PasswordHistoryService($passwordHasher, $this->config);
        $auditLogService ??= new AuditLogService($this->config);
        $createService ??= new CreateService();
        $expireService ??= new ExpireService($this->config);
        $authHelper ??= $this->createAuthHelper($currentUser);
        $confirmationService ??= new ConfirmationService(
            $this->eventDispatcher,
            new UserTokenFactory(),
            new MailService(
                $this->mailer,
                $this->config->mailPath,
                $translator,
                $this->url,
                $this->config->appName,
            ),
        );
        $blockService ??= new BlockService(
            $this->eventDispatcher,
            $this->createTerminateUserSessionsService(),
        );
        $recoveryService ??= new RecoveryService();
        $switchIdentityService ??= new SwitchIdentityService(
            $this->config,
            $currentUser,
            $this->session,
            $this->eventDispatcher,
        );
        $updateAssignmentsService ??= $this->createUpdateAssignmentsService();

        return new UserController(
            translator: $translator,
            viewRenderer: $viewRenderer,
            userCreateService: $createService,
            userBlockService: $blockService,
            userConfirmationService: $confirmationService,
            passwordRecoveryService: $recoveryService,
            passwordExpireService: $expireService,
            switchIdentityService: $switchIdentityService,
            updateAuthAssignmentsService: $updateAssignmentsService,
            authHelper: $authHelper,
            passwordGenerator: $passwordGenerator,
            validator: $validator,
            eventDispatcher: $this->eventDispatcher,
            url: $this->url,
            config: $this->config,
            hydrator: $hydrator,
            currentUser: $currentUser,
            responseFactory: $responseFactory,
            itemsStorage: $this->itemsStorage,
            assignmentsStorage: $this->assignmentsStorage,
            flash: $flash,
            passwordHistoryService: $passwordHistoryService,
            auditLogService: $auditLogService,
        );
    }

    public function getAssignmentsStorage(): AssignmentsStorageInterface
    {
        return $this->assignmentsStorage;
    }

    public function getAuthClientRegistry(): AuthClientRegistry
    {
        return $this->authClientRegistry;
    }

    public function getAuthManager(): ManagerInterface
    {
        return $this->authManager;
    }

    public function getConfig(): ModuleConfig
    {
        return $this->config;
    }

    public function getEventDispatcher(): EventCaptureDispatcher
    {
        return $this->eventDispatcher;
    }

    public function getItemsStorage(): ItemsStorageInterface
    {
        return $this->itemsStorage;
    }

    public function getMailer(): MailCapture
    {
        return $this->mailer;
    }

    public function getSession(): FakeSession
    {
        return $this->session;
    }

    public function getUrlGenerator(): FakeUrlGenerator
    {
        return $this->url;
    }

    private function createAuthHelper(CurrentUser $currentUser): AuthHelper
    {
        return new AuthHelper(
            $this->authManager,
            $this->itemsStorage,
            $this->assignmentsStorage,
            $this->config,
            $currentUser,
        );
    }

    private function createPasswordGenerator(): PasswordGeneratorInterface
    {
        return new RandomPasswordGenerator();
    }

    private function createTerminateUserSessionsService(): TerminateUserSessionsService
    {
        return new TerminateUserSessionsService();
    }

    private function createUpdateAssignmentsService(): UpdateAssignmentsService
    {
        return new UpdateAssignmentsService(
            $this->authManager,
            $this->assignmentsStorage,
            new ItemsValidator($this->itemsStorage),
        );
    }

}
