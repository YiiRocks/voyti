<?php

declare(strict_types=1);

use YiiRocks\Voyti\Controller;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

$routes = [
    Group::create('')
        ->routes(
            // Security
            Route::methods(['GET', 'POST'], 'login')->name('voyti/login')->action([Controller\SecurityController::class, 'login']),
            Route::post('logout')->name('voyti/logout')->action([Controller\SecurityController::class, 'logout']),
            Route::methods(['GET', 'POST'], 'confirm')->name('voyti/confirm')->action([Controller\SecurityController::class, 'confirm']),
            Route::get('auth/{provider}')->name('voyti/auth')->action([Controller\SecurityController::class, 'auth']),
            Route::get('auth/connect/{provider}')->name('voyti/connect')->action([Controller\SecurityController::class, 'connect']),

            // Registration
            Route::methods(['GET', 'POST'], 'register')->name('voyti/register')->action([Controller\RegistrationController::class, 'register']),
            Route::methods(['GET', 'POST'], 'confirm/{id:\d+}/{code}')->name('voyti/registration-confirm')->action([Controller\RegistrationController::class, 'confirm']),
            Route::methods(['GET', 'POST'], 'resend')->name('voyti/resend')->action([Controller\RegistrationController::class, 'resend']),
            Route::get('connect/{code}')->name('voyti/registration-connect')->action([Controller\RegistrationController::class, 'connect']),

            // Recovery
            Route::methods(['GET', 'POST'], 'forgot')->name('voyti/forgot')->action([Controller\RecoveryController::class, 'request']),
            Route::methods(['GET', 'POST'], 'recover/{id:\d+}/{code}')->name('voyti/recover')->action([Controller\RecoveryController::class, 'reset']),

            // Profile
            Route::get('profile/{id:\d+}')->name('voyti/profile')->action([Controller\ProfileController::class, 'show']),

            // Settings
            Route::methods(['GET', 'POST'], 'settings')->name('voyti/settings')->action([Controller\SettingsController::class, 'profile']),
            Route::methods(['GET', 'POST'], 'settings/account')->name('voyti/settings-account')->action([Controller\SettingsController::class, 'account']),
            Route::get('settings/networks')->name('voyti/settings-networks')->action([Controller\SettingsController::class, 'networks']),
            Route::get('settings/privacy')->name('voyti/settings-privacy')->action([Controller\SettingsController::class, 'privacy']),
            Route::methods(['GET', 'POST'], 'settings/gdpr-consent')->name('voyti/gdpr-consent')->action([Controller\SettingsController::class, 'gdprConsent']),
            Route::methods(['GET', 'POST'], 'settings/gdpr-delete')->name('voyti/gdpr-delete')->action([Controller\SettingsController::class, 'gdprDelete']),
            Route::post('settings/delete')->name('voyti/settings-delete')->action([Controller\SettingsController::class, 'delete']),
            Route::methods(['GET', 'POST'], 'settings/two-factor')->name('voyti/settings-two-factor')->action([Controller\SettingsController::class, 'twoFactor']),
            Route::post('settings/two-factor-enable')->name('voyti/settings-two-factor-enable')->action([Controller\SettingsController::class, 'twoFactorEnable']),
            Route::post('settings/two-factor-disable')->name('voyti/settings-two-factor-disable')->action([Controller\SettingsController::class, 'twoFactorDisable']),
            Route::get('settings/confirm/{code}')->name('voyti/settings-confirm')->action([Controller\SettingsController::class, 'confirm']),
            Route::post('settings/disconnect/{id:\d+}')->name('voyti/settings-disconnect')->action([Controller\SettingsController::class, 'disconnect']),
            Route::get('settings/export')->name('voyti/settings-export')->action([Controller\SettingsController::class, 'export']),

            // Admin
            Route::get('admin')->name('voyti/admin')->action([Controller\AdminController::class, 'index']),
            Route::methods(['GET', 'POST'], 'admin/create')->name('voyti/admin-create')->action([Controller\AdminController::class, 'create']),
            Route::methods(['GET', 'POST'], 'admin/update/{id:\d+}')->name('voyti/admin-update')->action([Controller\AdminController::class, 'update']),
            Route::methods(['GET', 'POST'], 'admin/update-profile/{id:\d+}')->name('voyti/admin-update-profile')->action([Controller\AdminController::class, 'updateProfile']),
            Route::get('admin/info/{id:\d+}')->name('voyti/admin-info')->action([Controller\AdminController::class, 'info']),
            Route::post('admin/confirm/{id:\d+}')->name('voyti/admin-confirm')->action([Controller\AdminController::class, 'confirm']),
            Route::post('admin/delete/{id:\d+}')->name('voyti/admin-delete')->action([Controller\AdminController::class, 'delete']),
            Route::post('admin/block/{id:\d+}')->name('voyti/admin-block')->action([Controller\AdminController::class, 'block']),
            Route::post('admin/switch-identity/{id:\d+}')->name('voyti/admin-switch')->action([Controller\AdminController::class, 'switchIdentity']),
            Route::post('admin/password-reset/{id:\d+}')->name('voyti/admin-password-reset')->action([Controller\AdminController::class, 'passwordReset']),
            Route::post('admin/force-password-change/{id:\d+}')->name('voyti/admin-force-password')->action([Controller\AdminController::class, 'forcePasswordChange']),
            Route::methods(['GET', 'POST'], 'admin/assignments/{id:\d+}')->name('voyti/admin-assignments')->action([Controller\AdminController::class, 'assignments']),
            Route::get('admin/session-history/{id:\d+}')->name('voyti/admin-session-history')->action([Controller\AdminController::class, 'sessionHistory']),
            Route::post('admin/terminate-sessions/{id:\d+}')->name('voyti/admin-terminate-sessions')->action([Controller\AdminController::class, 'terminateSessions']),

            // RBAC
            Route::get('permissions')->name('voyti/permissions')->action([Controller\PermissionController::class, 'index']),
            Route::methods(['GET', 'POST'], 'permissions/create')->name('voyti/permissions-create')->action([Controller\PermissionController::class, 'create']),
            Route::methods(['GET', 'POST'], 'permissions/update/{name}')->name('voyti/permissions-update')->action([Controller\PermissionController::class, 'update']),
            Route::post('permissions/delete/{name}')->name('voyti/permissions-delete')->action([Controller\PermissionController::class, 'delete']),
            Route::get('roles')->name('voyti/roles')->action([Controller\RoleController::class, 'index']),
            Route::methods(['GET', 'POST'], 'roles/create')->name('voyti/roles-create')->action([Controller\RoleController::class, 'create']),
            Route::methods(['GET', 'POST'], 'roles/update/{name}')->name('voyti/roles-update')->action([Controller\RoleController::class, 'update']),
            Route::post('roles/delete/{name}')->name('voyti/roles-delete')->action([Controller\RoleController::class, 'delete']),
            Route::get('rules')->name('voyti/rules')->action([Controller\RuleController::class, 'index']),
            Route::methods(['GET', 'POST'], 'rules/create')->name('voyti/rules-create')->action([Controller\RuleController::class, 'create']),
            Route::methods(['GET', 'POST'], 'rules/update/{name}')->name('voyti/rules-update')->action([Controller\RuleController::class, 'update']),
            Route::post('rules/delete/{name}')->name('voyti/rules-delete')->action([Controller\RuleController::class, 'delete']),
        ),
];

if ($params[ModuleConfig::class]->enableRestApi ?? false) {
    $routes[] = Group::create($params[ModuleConfig::class]->adminRestPrefix)
        ->routes(
            Route::get('users')->name('voyti/api-users-index')->action([Controller\api\v1\AdminController::class, 'index']),
            Route::get('users/{id:\d+}')->name('voyti/api-users-view')->action([Controller\api\v1\AdminController::class, 'view']),
            Route::post('users')->name('voyti/api-users-create')->action([Controller\api\v1\AdminController::class, 'create']),
            Route::put('users/{id:\d+}')->name('voyti/api-users-update')->action([Controller\api\v1\AdminController::class, 'update']),
            Route::delete('users/{id:\d+}')->name('voyti/api-users-delete')->action([Controller\api\v1\AdminController::class, 'delete']),
        );
}

return $routes;
