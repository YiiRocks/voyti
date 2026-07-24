<?php

declare(strict_types=1);

use YiiRocks\Voyti\Controller;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Middleware\RequireLoginMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use Yiisoft\Csrf\CsrfMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Session\SessionMiddleware;

$moduleConfig = new ModuleConfig(...($params['yiirocks/voyti'] ?? []));

$userRoutes = [
    Route::get('')->name('voyti/admin-users')->action([Controller\Admin\User\UserController::class, 'index']),
    Route::methods(['GET', 'POST'], 'create')->name('voyti/admin-users-create')->action([Controller\Admin\User\UserController::class, 'create']),
    Route::methods(['GET', 'POST'], 'update/{id:\d+}')->name('voyti/admin-users-update')->action([Controller\Admin\User\UserController::class, 'update']),
    Route::methods(['GET', 'POST'], 'update-profile/{id:\d+}')->name('voyti/admin-users-update-profile')->action([Controller\Admin\User\UserController::class, 'updateProfile']),
    Route::get('info/{id:\d+}')->name('voyti/admin-users-show')->action([Controller\Admin\User\UserController::class, 'show']),
    Route::post('confirm/{id:\d+}')->name('voyti/admin-users-confirm')->action([Controller\Admin\User\UserController::class, 'confirm']),
    Route::post('delete/{id:\d+}')->name('voyti/admin-users-delete')->action([Controller\Admin\User\UserController::class, 'delete']),
    Route::post('block/{id:\d+}')->name('voyti/admin-users-block')->action([Controller\Admin\User\UserController::class, 'block']),
    Route::post('password-reset/{id:\d+}')->name('voyti/admin-users-password-reset')->action([Controller\Admin\User\UserController::class, 'passwordReset']),
    Route::post('force-password-change/{id:\d+}')->name('voyti/admin-users-force-password-change')->action([Controller\Admin\User\UserController::class, 'forcePasswordChange']),
    Route::methods(['GET', 'POST'], 'assignments/{id:\d+}')->name('voyti/admin-users-assignments')->action([Controller\Admin\User\UserController::class, 'assignments']),
    Route::get('sessions/{id:\d+}')->name('voyti/admin-users-sessions')->action([Controller\Admin\User\UserController::class, 'sessions']),
    Route::post('terminate-sessions/{id:\d+}')->name('voyti/admin-users-terminate-sessions')->action([Controller\Admin\User\UserController::class, 'terminateSessions']),
];

if ($moduleConfig->enableSwitchIdentities) {
    $userRoutes[] = Route::post('switch-identity/{id:\d+}')->name('voyti/admin-users-switch-identity')->action([Controller\Admin\User\UserController::class, 'switchIdentity']);
}

$permissionRoutes = [
    Route::get('')->name('voyti/admin-rbac-permissions')
        ->action([Controller\Admin\Rbac\RbacController::class, 'index'])
        ->defaults(['itemType' => 'permission', 'indexRouteName' => 'admin-rbac-permissions']),
    Route::methods(['GET', 'POST'], 'create')->name('voyti/admin-rbac-permissions-create')
        ->action([Controller\Admin\Rbac\RbacController::class, 'create'])
        ->defaults(['itemType' => 'permission', 'indexRouteName' => 'admin-rbac-permissions']),
    Route::methods(['GET', 'POST'], 'update/{name}')->name('voyti/admin-rbac-permissions-update')
        ->action([Controller\Admin\Rbac\RbacController::class, 'update'])
        ->defaults(['itemType' => 'permission', 'indexRouteName' => 'admin-rbac-permissions']),
    Route::post('delete/{name}')->name('voyti/admin-rbac-permissions-delete')
        ->action([Controller\Admin\Rbac\RbacController::class, 'delete'])
        ->defaults(['itemType' => 'permission', 'indexRouteName' => 'admin-rbac-permissions']),
];

$roleRoutes = [
    Route::get('')->name('voyti/admin-rbac-roles')
        ->action([Controller\Admin\Rbac\RbacController::class, 'index'])
        ->defaults(['itemType' => 'role', 'indexRouteName' => 'admin-rbac-roles']),
    Route::methods(['GET', 'POST'], 'create')->name('voyti/admin-rbac-roles-create')
        ->action([Controller\Admin\Rbac\RbacController::class, 'create'])
        ->defaults(['itemType' => 'role', 'indexRouteName' => 'admin-rbac-roles']),
    Route::methods(['GET', 'POST'], 'update/{name}')->name('voyti/admin-rbac-roles-update')
        ->action([Controller\Admin\Rbac\RbacController::class, 'update'])
        ->defaults(['itemType' => 'role', 'indexRouteName' => 'admin-rbac-roles']),
    Route::post('delete/{name}')->name('voyti/admin-rbac-roles-delete')
        ->action([Controller\Admin\Rbac\RbacController::class, 'delete'])
        ->defaults(['itemType' => 'role', 'indexRouteName' => 'admin-rbac-roles']),
];

$ruleRoutes = [
    Route::get('')->name('voyti/admin-rbac-rules')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'index']),
    Route::methods(['GET', 'POST'], 'create')->name('voyti/admin-rbac-rules-create')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'create']),
    Route::methods(['GET', 'POST'], 'update/{name}')->name('voyti/admin-rbac-rules-update')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'update']),
    Route::post('delete/{name}')->name('voyti/admin-rbac-rules-delete')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'delete']),
];

$sessionRoutes = [
    Route::methods(['GET', 'POST'], 'login')->name('voyti/session-login')->action([Controller\Session\SessionController::class, 'login']),
    Route::methods(['GET', 'POST'], 'logout')->name('voyti/session-logout')->action([Controller\Session\SessionController::class, 'logout']),
    Route::methods(['GET', 'POST'], 'confirm')->name('voyti/session-confirm')->action([Controller\Session\SessionController::class, 'confirm']),
    Route::get('auth/{provider}')->name('voyti/session-auth')->action([Controller\Session\SessionController::class, 'auth']),
];

$registrationRoutes = [
    Route::methods(['GET', 'POST'], 'register')->name('voyti/registration-register')->action([Controller\Registration\RegistrationController::class, 'register']),
    Route::methods(['GET', 'POST'], 'confirm/{id:\d+}/{code}')->name('voyti/registration-confirm')->action([Controller\Registration\RegistrationController::class, 'confirm']),
    Route::methods(['GET', 'POST'], 'resend')->name('voyti/registration-resend')->action([Controller\Registration\RegistrationController::class, 'resend']),
    Route::get('connect/{code}')->name('voyti/registration-connect')->action([Controller\Registration\RegistrationController::class, 'connect']),
];

$passwordResetRoutes = [
    Route::methods(['GET', 'POST'], 'forgot')->name('voyti/password-reset-request')->action([Controller\PasswordReset\PasswordResetController::class, 'request']),
    Route::methods(['GET', 'POST'], 'recover/{id:\d+}/{code}')->name('voyti/password-reset-confirm')->action([Controller\PasswordReset\PasswordResetController::class, 'confirm']),
];

$settingsRoutes = [
    Route::get('')->name('voyti/user')->action([Controller\Settings\SettingsController::class, 'index']),
    Route::methods(['GET', 'POST'], 'profile')->name('voyti/user-profile')->action([Controller\Profile\ProfileController::class, 'update']),
    Route::methods(['GET', 'POST'], 'account')->name('voyti/user-account')->action([Controller\Account\AccountController::class, 'update']),
    Route::get('account/confirm/{code}')->name('voyti/user-account-confirm')->action([Controller\Account\AccountController::class, 'confirm']),
    Route::get('networks/')->name('voyti/user-social-network')->action([Controller\SocialNetwork\SocialNetworkController::class, 'index']),
    Route::post('networks/disconnect/{id:\d+}')->name('voyti/user-social-network-delete')->action([Controller\SocialNetwork\SocialNetworkController::class, 'delete']),
    Route::get('sessions/')->name('voyti/user-account-sessions')->action([Controller\Account\SessionController::class, 'index']),
    Route::post('sessions/terminate/{sessionId}')->name('voyti/user-account-sessions-terminate')->action([Controller\Account\SessionController::class, 'terminate']),
];

if ($moduleConfig->enableGdprCompliance || $moduleConfig->allowAccountDelete) {
    $settingsRoutes[] = Route::get('privacy/')->name('voyti/user-privacy')->action([Controller\Privacy\PrivacyController::class, 'index']);
}

if ($moduleConfig->enableGdprCompliance) {
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'privacy/gdpr-consent')->name('voyti/user-privacy-gdpr-consent')->action([Controller\Privacy\PrivacyController::class, 'gdprConsent']);
    $settingsRoutes[] = Route::get('privacy/export')->name('voyti/user-privacy-export')->action([Controller\Privacy\PrivacyController::class, 'export']);
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'privacy/anonymize')->name('voyti/user-privacy-anonymize')->action([Controller\Privacy\PrivacyController::class, 'anonymize']);
}

if ($moduleConfig->allowAccountDelete) {
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'privacy/delete')->name('voyti/user-privacy-delete')->action([Controller\Privacy\PrivacyController::class, 'delete']);
}

if ($moduleConfig->enableTwoFactorAuthentication) {
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'two-factor/')->name('voyti/user-two-factor')->action([Controller\TwoFactor\TwoFactorController::class, 'index']);
    $settingsRoutes[] = Route::post('two-factor/enable')->name('voyti/user-two-factor-enable')->action([Controller\TwoFactor\TwoFactorController::class, 'enable']);
    $settingsRoutes[] = Route::post('two-factor/disable/')->name('voyti/user-two-factor-disable')->action([Controller\TwoFactor\TwoFactorController::class, 'disable']);
    $settingsRoutes[] = Route::post('two-factor/disable/send-code')->name('voyti/user-two-factor-disable-send-code')->action([Controller\TwoFactor\TwoFactorController::class, 'disableSendCode']);
    $settingsRoutes[] = Route::get('two-factor/email/')->name('voyti/user-two-factor-email')->action([Controller\TwoFactor\TwoFactorController::class, 'email']);
    $settingsRoutes[] = Route::post('two-factor/email/send-code')->name('voyti/user-two-factor-email-send-code')->action([Controller\TwoFactor\TwoFactorController::class, 'sendEmailCode']);
    if ((new QrCodeUriGeneratorService($moduleConfig))->isAvailable()) {
        $settingsRoutes[] = Route::get('two-factor/google/')->name('voyti/user-two-factor-google')->action([Controller\TwoFactor\TwoFactorController::class, 'google']);
        $settingsRoutes[] = Route::post('two-factor/google/renew')->name('voyti/user-two-factor-google-renew')->action([Controller\TwoFactor\TwoFactorController::class, 'renew']);
    }
    $settingsRoutes[] = Route::post('two-factor/backup-codes/regenerate')->name('voyti/user-two-factor-regenerate-backup-codes')->action([Controller\TwoFactor\TwoFactorController::class, 'regenerateBackupCodes']);
}

$routes = [
    // Public profile view (not part of the settings/ resource — no auth required)
    Route::get('profile/{id:\d+}')->name('voyti/profile')->action([Controller\Profile\ProfileController::class, 'show']),

    ...$sessionRoutes,
    ...$registrationRoutes,
    ...$passwordResetRoutes,

    Group::create('settings/')->middleware(RequireLoginMiddleware::class)->routes(...$settingsRoutes),

    // Admin + RBAC
    Group::create('admin/')
        ->middleware(AccessRuleMiddleware::class)
        ->routes(
            Route::get('')
                ->name('voyti/admin')
                ->action([Controller\Admin\Dashboard\DashboardController::class, 'index']),
            Group::create('users/')->routes(...$userRoutes),
            Group::create('rbac/')->routes(
                Group::create('permissions/')->routes(...$permissionRoutes),
                Group::create('roles/')->routes(...$roleRoutes),
                Group::create('rules/')->routes(...$ruleRoutes),
            ),
            Group::create('audit-log/')->routes(
                Route::get('')->name('voyti/admin-audit-log')->action([Controller\Admin\AuditLog\AuditLogController::class, 'index']),
            ),
        ),
];

if ($moduleConfig->enableSwitchIdentities) {
    // Not admin-gated: restoring must remain reachable while impersonating a non-admin user.
    $routes[] = Route::post('admin/users/switch-identity/restore')->name('voyti/admin-users-switch-identity-restore')->action([Controller\Admin\User\UserController::class, 'switchIdentityRestore']);
}

$webMiddlewares = [SessionMiddleware::class, RememberMeMiddleware::class, CsrfMiddleware::class, SessionRevocationEnforceMiddleware::class];
if ($moduleConfig->enablePasswordExpiration) {
    $webMiddlewares[] = PasswordAgeEnforceMiddleware::class;
}
if ($moduleConfig->enableTwoFactorAuthentication) {
    $webMiddlewares[] = TwoFactorAuthenticationEnforceMiddleware::class;
}

$result = [
    Group::create()
        ->middleware(...$webMiddlewares)
        ->routes(...$routes),
];

if ($moduleConfig->enableRestApi) {
    $result[] = Group::create($moduleConfig->adminRestPrefix . '/')
        ->middleware(JsonDataResponseMiddleware::class)
        ->routes(
            Route::get('openapi.json')->name('voyti/api-openapi')->action([Controller\api\OpenApiController::class, 'index']),
            Group::create('v1/')
                ->middleware(ApiTokenAuthenticationMiddleware::class, AccessRuleMiddleware::class)
                ->routes(
                    Route::get('users')->name('voyti/api-v1-users-index')->action([Controller\api\v1\User\UserController::class, 'index']),
                    Route::get('users/{id:\d+}')->name('voyti/api-v1-users-view')->action([Controller\api\v1\User\UserController::class, 'view']),
                    Route::post('users')->name('voyti/api-v1-users-create')->action([Controller\api\v1\User\UserController::class, 'create']),
                    Route::patch('users/{id:\d+}')->name('voyti/api-v1-users-update')->action([Controller\api\v1\User\UserController::class, 'update']),
                    Route::delete('users/{id:\d+}')->name('voyti/api-v1-users-delete')->action([Controller\api\v1\User\UserController::class, 'delete']),
                ),
        );
}

return $result;
