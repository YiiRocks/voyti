<?php

declare(strict_types=1);

use YiiRocks\Voyti\Controller;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Csrf\CsrfMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Session\SessionMiddleware;
use Yiisoft\Yii\Middleware\Redirect;

$moduleConfig = ModuleConfig::fromArray($params['yiirocks/voyti'] ?? []);

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
    Route::get('session-history/{id:\d+}')->name('voyti/admin-users-session-history')->action([Controller\Admin\User\UserController::class, 'sessionHistory']),
    Route::post('terminate-sessions/{id:\d+}')->name('voyti/admin-users-terminate-sessions')->action([Controller\Admin\User\UserController::class, 'terminateSessions']),
];

if ($moduleConfig->enableSwitchIdentities) {
    $userRoutes[] = Route::post('switch-identity/{id:\d+}')->name('voyti/admin-users-switch-identity')->action([Controller\Admin\User\UserController::class, 'switchIdentity']);
}

$permissionRoutes = [
    Route::methods(['GET', 'POST'], 'create')->name('voyti/admin-rbac-permissions-create')->action([Controller\Admin\Rbac\Permission\PermissionController::class, 'create']),
    Route::methods(['GET', 'POST'], 'update/{name}')->name('voyti/admin-rbac-permissions-update')->action([Controller\Admin\Rbac\Permission\PermissionController::class, 'update']),
    Route::post('delete/{name}')->name('voyti/admin-rbac-permissions-delete')->action([Controller\Admin\Rbac\Permission\PermissionController::class, 'delete']),
];

$roleRoutes = [
    Route::methods(['GET', 'POST'], 'create')->name('voyti/admin-rbac-roles-create')->action([Controller\Admin\Rbac\Role\RoleController::class, 'create']),
    Route::methods(['GET', 'POST'], 'update/{name}')->name('voyti/admin-rbac-roles-update')->action([Controller\Admin\Rbac\Role\RoleController::class, 'update']),
    Route::post('delete/{name}')->name('voyti/admin-rbac-roles-delete')->action([Controller\Admin\Rbac\Role\RoleController::class, 'delete']),
];

$ruleRoutes = [
    Route::methods(['GET', 'POST'], 'create')->name('voyti/admin-rbac-rules-create')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'create']),
    Route::methods(['GET', 'POST'], 'update/{name}')->name('voyti/admin-rbac-rules-update')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'update']),
    Route::post('delete/{name}')->name('voyti/admin-rbac-rules-delete')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'delete']),
];

$rbacRoutes = [
    Route::get('permissions/')->name('voyti/admin-rbac-permissions')->action([Controller\Admin\Rbac\Permission\PermissionController::class, 'index']),
    Group::create('permissions/')->routes(...$permissionRoutes),
    Route::get('roles/')->name('voyti/admin-rbac-roles')->action([Controller\Admin\Rbac\Role\RoleController::class, 'index']),
    Group::create('roles/')->routes(...$roleRoutes),
    Route::get('rules/')->name('voyti/admin-rbac-rules')->action([Controller\Admin\Rbac\Rule\RuleController::class, 'index']),
    Group::create('rules/')->routes(...$ruleRoutes),
];

$sessionRoutes = [
    Route::methods(['GET', 'POST'], 'login')->name('voyti/session-login')->action([Controller\Session\SessionController::class, 'login']),
    Route::methods(['GET', 'POST'], 'logout')->name('voyti/session-logout')->action([Controller\Session\SessionController::class, 'logout']),
    Route::methods(['GET', 'POST'], 'confirm')->name('voyti/session-confirm')->action([Controller\Session\SessionController::class, 'confirm']),
    Route::get('auth/{provider}')->name('voyti/session-auth')->action([Controller\Session\SessionController::class, 'auth']),
    Route::get('auth/connect/{provider}')->name('voyti/session-connect')->action([Controller\Session\SessionController::class, 'connect']),
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
    Route::methods(['GET', 'POST'], 'account')->name('voyti/account-update')->action([Controller\Account\AccountController::class, 'update']),
    Route::get('confirm/{code}')->name('voyti/account-confirm')->action([Controller\Account\AccountController::class, 'confirm']),
    Route::get('networks/')->name('voyti/social-network')->action([Controller\SocialNetwork\SocialNetworkController::class, 'index']),
    Route::post('networks/disconnect/{id:\d+}')->name('voyti/social-network-delete')->action([Controller\SocialNetwork\SocialNetworkController::class, 'delete']),
    Route::get('sessions/')->name('voyti/account-sessions')->action([Controller\Account\SessionController::class, 'index']),
    Route::post('sessions/terminate/{sessionId}')->name('voyti/account-sessions-terminate')->action([Controller\Account\SessionController::class, 'terminate']),
];

if ($moduleConfig->enableGdprCompliance || $moduleConfig->allowAccountDelete) {
    $settingsRoutes[] = Route::get('privacy/')->name('voyti/privacy')->action([Controller\Privacy\PrivacyController::class, 'index']);
}

if ($moduleConfig->enableGdprCompliance) {
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'privacy/gdpr-consent')->name('voyti/privacy-gdpr-consent')->action([Controller\Privacy\PrivacyController::class, 'gdprConsent']);
    $settingsRoutes[] = Route::get('privacy/export')->name('voyti/privacy-export')->action([Controller\Privacy\PrivacyController::class, 'export']);
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'privacy/anonymize')->name('voyti/privacy-anonymize')->action([Controller\Privacy\PrivacyController::class, 'anonymize']);
}

if ($moduleConfig->allowAccountDelete) {
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'privacy/delete')->name('voyti/privacy-delete')->action([Controller\Privacy\PrivacyController::class, 'delete']);
}

if ($moduleConfig->enableTwoFactorAuthentication) {
    $settingsRoutes[] = Route::methods(['GET', 'POST'], 'two-factor/')->name('voyti/two-factor')->action([Controller\TwoFactor\TwoFactorController::class, 'index']);
    $settingsRoutes[] = Route::get('two-factor-google/')->name('voyti/two-factor-google')->action([Controller\TwoFactor\TwoFactorController::class, 'google']);
    $settingsRoutes[] = Route::get('two-factor-email/')->name('voyti/two-factor-email')->action([Controller\TwoFactor\TwoFactorController::class, 'email']);
    $settingsRoutes[] = Route::post('two-factor-google/enable')->name('voyti/two-factor-enable')->action([Controller\TwoFactor\TwoFactorController::class, 'enable']);
    $settingsRoutes[] = Route::post('two-factor/disable/')->name('voyti/two-factor-disable')->action([Controller\TwoFactor\TwoFactorController::class, 'disable']);
    $settingsRoutes[] = Route::post('two-factor/disable/send-code')->name('voyti/two-factor-disable-send-code')->action([Controller\TwoFactor\TwoFactorController::class, 'disableSendCode']);
    $settingsRoutes[] = Route::post('two-factor-google/renew')->name('voyti/two-factor-renew')->action([Controller\TwoFactor\TwoFactorController::class, 'renew']);
    $settingsRoutes[] = Route::post('two-factor-email/send-code')->name('voyti/two-factor-send-email-code')->action([Controller\TwoFactor\TwoFactorController::class, 'sendEmailCode']);
    $settingsRoutes[] = Route::post('two-factor/backup-codes/regenerate')->name('voyti/two-factor-regenerate-backup-codes')->action([Controller\TwoFactor\TwoFactorController::class, 'regenerateBackupCodes']);
}

$routes = [
    // Public profile view (not part of the settings/ resource — no auth required)
    Route::get('profile/{id:\d+}')->name('voyti/profile')->action([Controller\Profile\ProfileController::class, 'show']),

    ...$sessionRoutes,
    ...$registrationRoutes,
    ...$passwordResetRoutes,

    Route::methods(['GET', 'POST'], 'settings/')->name('voyti/profile-update')->action([Controller\Profile\ProfileController::class, 'update']),
    Group::create('settings/')->routes(...$settingsRoutes),

    // Admin + RBAC
    Group::create('admin/')
        ->middleware(AccessRuleMiddleware::class)
        ->routes(
            Route::get('')
                ->name('voyti/admin')
                ->action(fn (Redirect $redirect) => $redirect->toRoute('voyti/admin-users')->temporary()),
            Group::create('users/')->routes(...$userRoutes),
            Group::create('rbac/')->routes(...$rbacRoutes),
            Group::create('audit-log/')->routes(
                Route::get('')->name('voyti/admin-audit-log')->action([Controller\Admin\AuditLog\AuditLogController::class, 'index']),
            ),
        ),
];

if ($moduleConfig->enableSwitchIdentities) {
    // Not admin-gated: restoring must remain reachable while impersonating a non-admin user.
    $routes[] = Route::post('admin/users/switch-identity/restore')->name('voyti/admin-users-switch-identity-restore')->action([Controller\Admin\User\UserController::class, 'switchIdentityRestore']);
}

$webMiddlewares = [SessionMiddleware::class, CsrfMiddleware::class];
if ($moduleConfig->enableSessionHistory) {
    $webMiddlewares[] = SessionRevocationEnforceMiddleware::class;
}
if ($moduleConfig->enablePasswordExpiration) {
    $webMiddlewares[] = PasswordAgeEnforceMiddleware::class;
}

$result = [
    Group::create()
        ->middleware(...$webMiddlewares)
        ->routes(...$routes),
];

if ($moduleConfig->enableRestApi) {
    $result[] = Group::create($moduleConfig->adminRestPrefix . '/v1/')
        ->middleware(ApiTokenAuthenticationMiddleware::class, AccessRuleMiddleware::class, JsonDataResponseMiddleware::class)
        ->routes(
            Route::get('users')->name('voyti/api-v1-users-index')->action([Controller\api\v1\User\UserController::class, 'index']),
            Route::get('users/{id:\d+}')->name('voyti/api-v1-users-view')->action([Controller\api\v1\User\UserController::class, 'view']),
            Route::post('users')->name('voyti/api-v1-users-create')->action([Controller\api\v1\User\UserController::class, 'create']),
            Route::put('users/{id:\d+}')->name('voyti/api-v1-users-update')->action([Controller\api\v1\User\UserController::class, 'update']),
            Route::delete('users/{id:\d+}')->name('voyti/api-v1-users-delete')->action([Controller\api\v1\User\UserController::class, 'delete']),
        );
}

return $result;
