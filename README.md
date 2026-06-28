# Voyti — Yii3 User Management Extension

> **войти**  
> ***/vɐjˈtʲi/***  
> *verb*
>
> "to enter" or "to log in"

Highly customizable and extensible user management, authentication, and authorization extension for Yii3.

Ported from [2amigos/yii2-usuario](https://github.com/2amigos/yii2-usuario) and rebuilt for Yii3 with PSR-15 middleware, PSR-11 DI, ActiveRecord entities, FormModel forms, and the `yiisoft/rbac` package.

[![Packagist Version](https://img.shields.io/packagist/v/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/yiirocks/voyti.svg)](https://php.net/)
[![Packagist](https://img.shields.io/packagist/dt/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![GitHub](https://img.shields.io/github/license/yiirocks/voyti.svg)](https://github.com/yiirocks/voyti/blob/master/LICENSE)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/yiirocks/voyti/build.yml?branch=main)](https://github.com/yiirocks/voyti/actions)

---

## Table of Contents

1. [Features](#features)
2. [Quick Start](#quick-start)
3. [Configuration](#configuration)
4. [Social Authentication](#social-authentication)
5. [Middleware](#middleware)
6. [RBAC](#rbac)
7. [Routes](#routes)
8. [Testing](#testing)

## Features

- **User Management** — Registration, email confirmation, login/logout with remember-me, password recovery, password expiration
- **Profile Management** — User profiles with gravatar, timezone, social links
- **Social Authentication** — Various built-in auth clients as [listed below](#social-authentication)
- **Two-Factor Authentication** — TOTP (authenticator app), email, and SMS 2FA with enforced-per-permission support
- **RBAC Management** — Full admin UI for roles, permissions, and rules with parent-child hierarchy, assignment management, and filtering
- **Session Management** — Session history tracking and termination
- **GDPR Compliance** — Consent management, data export, anonymized deletion with admin notification
- **Password Policies** — Minimum complexity requirements, max age enforcement via middleware
- **Email Change Strategies** — Three modes: insecure (immediate), default (confirm new address), secure (confirm both old and new)
- **REST API** — Optional JSON API for user CRUD
- **CAPTCHA** — Optional reCAPTCHA v2/v3 integration via `yiirocks/recaptcha`
- **i18n** — Built-in translations for multiple languages
- **Themed Views** — Bootstrap 5 views shipped by default; mail templates separate and independently overridable

## Requirements

- PHP >= 8.3
- Yii3 packages (yiisoft/db, yiisoft/rbac, yiisoft/view, yiisoft/validator, etc.)

## Quick Start

### 1. Install

```bash
composer require yiirocks/voyti
```

For reCAPTCHA support (optional):

```bash
composer require yiirocks/recaptcha
```

For 2FA TOTP support (optional):

```bash
composer require chillerlan/php-authenticator chillerlan/php-qrcode
```

### 2. Run migrations

Voyti provides its migration path through `config/params-console.php` using the
standard `yiisoft/db-migration` configuration keys. With `yiisoft/db-migration`
enabled in your console app, run:

```bash
./yii migrate:up
```

One migration creates the `user`, `user_profile`, `user_social_account`,
`user_token`, and `user_session_history` tables with all columns (2FA, GDPR,
password expiration, last login IP, etc.) included.

### 3. Register routes

Routes are **not** auto-registered — you must add them to your router configuration.

Pull the `voyti-routes` config group into your router definition. The example below mounts them under a `user/` prefix — omit or change the prefix as needed:

```php
use Yiisoft\Config\Config;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Router\Group;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteCollector;

/** @var Config $config */

return [
    RouteCollectionInterface::class => [
        'class' => RouteCollection::class,
        '__construct()' => [
            'collector' => DynamicReference::to(
                static fn() => (new RouteCollector())
                    ->addRoute(
                        Group::create('/')
                            ->routes(...[
                                ...$config->get("routes"), // your routes
                                Group::create('user/')
                                    ->routes(...$config->get("voyti-routes"))
                            ]),
                    )
            ),
        ],
    ],
];
```

When `enableRestApi` is `true`, the API routes are mounted under `adminRestPrefix` and expose user CRUD endpoints.

### 4. Done

DI bindings, event listeners, and console commands are auto-registered via the
[Yii3 config plugin](https://github.com/yiisoft/config). No manual wiring needed.

## Configuration

Override Voyti params in your app's `config/params.php` using the
`yiirocks/voyti` key:

```php
return [
    'yiirocks/voyti' => [
        'enableTwoFactorAuthentication' => true,
        'recaptchaVersion' => 'v3',
    ],
];
```

Below are all top-level `yiirocks/voyti` options, followed by the nested `mailParams` and `socialNetworkClients` options.

### Authentication & Registration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `loginRoute` | `string` | `'voyti/login'` | Login route name |
| `accountSettingsRoute` | `string` | `'voyti/settings-account'` | Route for account settings redirects |
| `enableRegistration` | `bool` | `true` | Allow new user registration |
| `enableSocialNetworkRegistration` | `bool` | `true` | Allow social network registration |
| `socialNetworkClients` | `array` | `[]` | OAuth client IDs, secrets, and provider-specific options |
| `enableEmailConfirmation` | `bool` | `true` | Require email confirmation |
| `generatePasswords` | `bool` | `false` | Auto-generate passwords on registration |
| `allowPasswordRecovery` | `bool` | `true` | Allow password recovery |
| `allowAdminPasswordRecovery` | `bool` | `true` | Allow admin-initiated password recovery |
| `allowAccountDelete` | `bool` | `false` | Allow users to delete their account |
| `emailChangeStrategy` | `int` | `1` | 0=insecure, 1=default, 2=secure |
| `rememberLoginLifespan` | `int` | `1209600` | Remember-me cookie lifetime and idle auth timeout in seconds |
| `tokenConfirmationLifespan` | `int` | `86400` | Confirmation token validity |
| `tokenRecoveryLifespan` | `int` | `21600` | Recovery token validity |
| `enableSwitchIdentities` | `bool` | `true` | Allow admin to switch user identities |
| `switchIdentitySessionKey` | `?string` | `'voyti_original_user'` | Session key for switched identity |
| `mailAdminOnRegister` | `?string` | `null` | Email notified on new registration |
| `recaptchaVersion` | `?string` | `null` | `'v2'`, `'v3'`, or `null` to disable |

### Two-Factor Authentication

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableTwoFactorAuthentication` | `bool` | `false` | Enable 2FA |
| `twoFactorAuthenticationForcedPermissions` | `array` | `[]` | Permissions that require 2FA |

```php
'yiirocks/voyti' => [
    'enableTwoFactorAuthentication' => true,
    'twoFactorAuthenticationForcedPermissions' => ['admin'],
],
```

### GDPR

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableGdprCompliance` | `bool` | `false` | Enable GDPR features |
| `gdprAnonymizePrefix` | `string` | `'GDPR'` | Prefix for anonymized usernames |
| `gdprExportProperties` | `array` | `['email', 'username', 'userProfile.public_email', 'userProfile.name', 'userProfile.gravatar_email', 'userProfile.location', 'userProfile.website', 'userProfile.bio']` | Properties included in data export |

```php
'yiirocks/voyti' => [
    'enableGdprCompliance' => true,
    'gdprAnonymizePrefix' => 'ANON-',
],
```

### Session & Security

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableSessionHistory` | `bool` | `false` | Track session history |
| `numberSessionHistory` | `int\|false` | `false` | Max sessions to keep per user |
| `disableIpLogging` | `bool` | `false` | Disable IP address logging |
| `enablePasswordExpiration` | `bool` | `false` | Enable password expiration |
| `maxPasswordAge` | `?int` | `null` | Max password age in days |
| `administratorPermissionName` | `?string` | `null` | Permission name for admin access |
| `profileVisibility` | `int` | `0` | Profile visibility level |

### Views & Mail

| Option | Type | Default | Description |
|--------|------|--------|--------------|
| `viewPath` | `string` | `__DIR__ . '/resources/views/bootstrap5'` | Base path for web templates |
| `mailPath` | `string` | `__DIR__ . '/resources/mail'` | Base path for mail templates |
| `mailParams` | `array` | see below | Mail from address and subjects |

Mail params defaults:

```php
[
    'fromEmail' => 'no-reply@example.com',
    'welcomeMailSubject' => 'Welcome to {app}',
    'confirmationMailSubject' => 'Confirm account on {app}',
    'reconfirmationMailSubject' => 'Confirm email change on {app}',
    'recoveryMailSubject' => 'Complete password reset on {app}',
    'twoFactorMailSubject' => 'Code for two factor authentication on {app}',
]
```

`mailParams` accepts the following keys:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `fromEmail` | `string` | `'no-reply@example.com'` | Sender address for Voyti mail |
| `welcomeMailSubject` | `string` | `'Welcome to {app}'` | Welcome email subject |
| `confirmationMailSubject` | `string` | `'Confirm account on {app}'` | Registration confirmation subject |
| `reconfirmationMailSubject` | `string` | `'Confirm email change on {app}'` | Email change confirmation subject |
| `recoveryMailSubject` | `string` | `'Complete password reset on {app}'` | Password recovery subject |
| `twoFactorMailSubject` | `string` | `'Code for two factor authentication on {app}'` | 2FA email code subject |

### REST API

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableRestApi` | `bool` | `false` | Enable REST API |
| `adminRestPrefix` | `string` | `'api/v1'` | REST API URL prefix |

### Social Authentication Providers

`socialNetworkClients` is a keyed array where each key is a provider name such as `github`, `google`, or `keycloak`.

Every provider accepts these options unless noted otherwise:

| Option | Type | Required | Default | Description |
|---|---|---|---:|---|
| `clientId` | `string` | yes | none | OAuth client/application ID issued by the provider |
| `clientSecret` | `string` | yes | none | OAuth client secret issued by the provider |
| `redirectUri` | `string` | no | generated callback URL | Overrides the callback URL; otherwise Voyti uses the absolute route URL for `voyti/auth` or `voyti/connect` |
| `scope` | `string` | no | provider default | Replaces the built-in default scope string |
| `enabled` | `bool` | no | `true` | If `false`, the provider is not registered and no button is rendered |
| `authorizationParams` | `array<string, scalar>` | no | `[]` | Extra query parameters appended to the authorization request |
| `tokenParams` | `array<string, scalar>` | no | `[]` | Extra fields merged into the token exchange request body |
| `userInfoQuery` | `array<string, scalar>` | no | `[]` | Extra query parameters merged into the user-info request |

Only Keycloak adds recognized extra options:

| Provider key | Extra option | Type | Required | Description |
|---|---|---|---:|---|
| `keycloak` | `baseUrl` | `string` | yes | Base Keycloak URL, for example `https://sso.example.com` |
| `keycloak` | `realm` | `string` | yes | Keycloak realm name used to build auth, token, and userinfo endpoints |

## Social Authentication

Various auth clients are included. Each implements the auth client interface and maps provider attributes to the `SocialNetworkAccount` entity:

- Facebook, GitHub, Google, Keycloak, LinkedIn, Microsoft365, VKontakte, X (formerly Twitter), Yandex

The `SocialAuthProviderService` handles the OAuth redirect/callback flow. The `UserSocialAuthenticateService` handles account lookup, creation, and user login. The `UserSocialAccountConnectService` links a social account to an existing user.

### Built-in defaults by provider

The following table shows Voyti's built-in endpoints and scopes. These are used unless you override `scope` or `redirectUri`.

| Provider key | Default scope | Authorization URL | Token URL | User info URL |
|---|---|---|---|---|
| `facebook` | `email` | `https://www.facebook.com/v19.0/dialog/oauth` | `https://graph.facebook.com/v19.0/oauth/access_token` | `https://graph.facebook.com/me` |
| `github` | `user:email` | `https://github.com/login/oauth/authorize` | `https://github.com/login/oauth/access_token` | `https://api.github.com/user` |
| `google` | `openid email profile` | `https://accounts.google.com/o/oauth2/v2/auth` | `https://oauth2.googleapis.com/token` | `https://openidconnect.googleapis.com/v1/userinfo` |
| `keycloak` | `openid email profile` | `{baseUrl}/realms/{realm}/protocol/openid-connect/auth` | `{baseUrl}/realms/{realm}/protocol/openid-connect/token` | `{baseUrl}/realms/{realm}/protocol/openid-connect/userinfo` |
| `linkedin` | `openid profile email` | `https://www.linkedin.com/oauth/v2/authorization` | `https://www.linkedin.com/oauth/v2/accessToken` | `https://api.linkedin.com/v2/userinfo` |
| `microsoft365` | `openid profile email User.Read` | `https://login.microsoftonline.com/common/oauth2/v2.0/authorize` | `https://login.microsoftonline.com/common/oauth2/v2.0/token` | `https://graph.microsoft.com/oidc/userinfo` |
| `vkontakte` | `email` | `https://oauth.vk.com/authorize` | `https://oauth.vk.com/access_token` | `https://api.vk.com/method/users.get` |
| `x` | `tweet.read users.read offline.access` | `https://twitter.com/i/oauth2/authorize` | `https://api.twitter.com/2/oauth2/token` | `https://api.twitter.com/2/users/me` |
| `yandex` | `login:email login:info` | `https://oauth.yandex.com/authorize` | `https://oauth.yandex.com/token` | `https://login.yandex.ru/info` |

### Provider-specific built-in request behavior

Voyti also applies a few provider-specific defaults during the user-info step:

| Provider key | Built-in behavior |
|---|---|
| `facebook` | Sends `access_token` and `fields=id,name,email` on the user-info request. |
| `github` | If `/user` does not include an email, Voyti also requests `https://api.github.com/user/emails` and picks the first primary or verified address. |
| `vkontakte` | Sends `access_token`, `fields=screen_name`, and `v=5.199`; email is read from the token response when present. |
| `x` | Sends `user.fields=id,name,username,profile_image_url`; normalized social identity does **not** include email (X API v2 does not expose the user's email address without elevated access). |
| `yandex` | Sends `format=json` on the user-info request. |

### Example

```php
return [
    'yiirocks/voyti' => [
        'socialNetworkClients' => [
            'github' => [
                'clientId' => $_ENV['GITHUB_CLIENT_ID'] ?? '',
                'clientSecret' => $_ENV['GITHUB_CLIENT_SECRET'] ?? '',
            ],
            'google' => [
                'clientId' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
                'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
            ],
    ],
];
```

With credentials configured:

1. The login page shows social login buttons for configured providers.
2. `settings/networks` lists connected providers and renders connect buttons for the remaining configured providers.
3. New social identities redirect to the registration connect screen, where users can log in to an existing account or register a new one before the identity is linked.

## Console commands

| Command | Description |
|---------|-------------|
| `voyti:create` | Create a new user |
| `voyti:delete` | Delete a user |
| `voyti:confirm` | Confirm a user's email |
| `voyti:password` | Change a user's password |

## Middleware

The extension ships three PSR-15 middleware classes for access control:

| Middleware | Description |
|-----------|-------------|
| `AccessRuleMiddleware` | Redirects guests to `loginRoute`; checks `administratorPermissionName` for admin access |
| `PasswordAgeEnforceMiddleware` | Redirects to `accountSettingsRoute` when `maxPasswordAge` is exceeded |
| `TwoFactorAuthenticationEnforceMiddleware` | Redirects to `accountSettingsRoute` when required permissions are assigned |

Register them in your application's middleware pipeline as needed.
The two redirect targets (`loginRoute` and `accountSettingsRoute`) are configurable via `ModuleConfig`, so you can map them to your own route structure.

## RBAC

Built on [`yiisoft/rbac`](https://github.com/yiisoft/rbac). The extension provides:

- **Admin UI** for managing permissions, roles, and rules (create, update, delete, filter)
- **Assignment management** — assign/revoke roles and permissions per user from the admin panel
- **Parent-child hierarchy** — roles can have child permissions/roles
- **Rule management** — register and manage custom `RuleInterface` classes

Default roles are configured in `config/rbac.php`:

```php
return [
    'rbac' => [
        'guest' => [],
        'user' => [],
        'admin' => [],
    ],
];
```

## Routes

The library does not provide a menu model or navigation contract. It only exposes named routes that the host application can use in its own menu, sidebar, or access rules.

| Route name | Method | Path | Purpose |
|------------|--------|------|---------|
| `voyti/login` | `GET`, `POST` | `login` | User login |
| `voyti/logout` | `GET`, `POST` | `logout` | User logout |
| `voyti/confirm` | `GET`, `POST` | `confirm` | Two-factor confirmation step |
| `voyti/auth` | `GET` | `auth/{provider}` | Social auth callback |
| `voyti/connect` | `GET` | `auth/connect/{provider}` | Social account connect callback |
| `voyti/register` | `GET`, `POST` | `register` | New user registration |
| `voyti/registration-confirm` | `GET`, `POST` | `confirm/{id}/{code}` | Email confirmation link |
| `voyti/resend` | `GET`, `POST` | `resend` | Resend confirmation email |
| `voyti/registration-connect` | `GET` | `connect/{code}` | Social registration link |
| `voyti/forgot` | `GET`, `POST` | `forgot` | Password recovery request |
| `voyti/recover` | `GET`, `POST` | `recover/{id}/{code}` | Password reset |
| `voyti/profile` | `GET` | `profile/{id}` | Public user profile |
| `voyti/settings` | `GET`, `POST` | `settings` | Profile settings |
| `voyti/settings-account` | `GET`, `POST` | `settings/account` | Account settings |
| `voyti/settings-networks` | `GET` | `settings/networks` | Linked social networks |
| `voyti/settings-privacy` | `GET` | `settings/privacy` | Privacy settings |
| `voyti/gdpr-consent` | `GET`, `POST` | `settings/gdpr-consent` | GDPR consent |
| `voyti/gdpr-delete` | `GET`, `POST` | `settings/gdpr-delete` | GDPR data removal |
| `voyti/settings-delete` | `POST` | `settings/delete` | Account deletion |
| `voyti/settings-two-factor` | `GET`, `POST` | `settings/two-factor` | Two-factor setup |
| `voyti/settings-two-factor-enable` | `POST` | `settings/two-factor-enable` | Enable 2FA |
| `voyti/settings-two-factor-disable` | `POST` | `settings/two-factor-disable` | Disable 2FA |
| `voyti/settings-confirm` | `GET` | `settings/confirm/{code}` | Confirm account changes |
| `voyti/settings-disconnect` | `POST` | `settings/disconnect/{id}` | Disconnect social account |
| `voyti/settings-export` | `GET` | `settings/export` | Export user data |
| `voyti/admin` | `GET` | `admin` | Admin dashboard |
| `voyti/admin-create` | `GET`, `POST` | `admin/create` | Create user |
| `voyti/admin-update` | `GET`, `POST` | `admin/update/{id}` | Update user |
| `voyti/admin-update-profile` | `GET`, `POST` | `admin/update-profile/{id}` | Update user profile |
| `voyti/admin-info` | `GET` | `admin/info/{id}` | User details |
| `voyti/admin-confirm` | `POST` | `admin/confirm/{id}` | Confirm user |
| `voyti/admin-delete` | `POST` | `admin/delete/{id}` | Delete user |
| `voyti/admin-block` | `POST` | `admin/block/{id}` | Block user |
| `voyti/admin-switch` | `POST` | `admin/switch-identity/{id}` | Switch identity |
| `voyti/admin-password-reset` | `POST` | `admin/password-reset/{id}` | Send password reset |
| `voyti/admin-force-password` | `POST` | `admin/force-password-change/{id}` | Force password change |
| `voyti/admin-assignments` | `GET`, `POST` | `admin/assignments/{id}` | Manage RBAC assignments |
| `voyti/admin-session-history` | `GET` | `admin/session-history/{id}` | Session history |
| `voyti/admin-terminate-sessions` | `POST` | `admin/terminate-sessions/{id}` | Terminate sessions |
| `voyti/permissions` | `GET` | `permissions` | List permissions |
| `voyti/permissions-create` | `GET`, `POST` | `permissions/create` | Create permission |
| `voyti/permissions-update` | `GET`, `POST` | `permissions/update/{name}` | Update permission |
| `voyti/permissions-delete` | `POST` | `permissions/delete/{name}` | Delete permission |
| `voyti/roles` | `GET` | `roles` | List roles |
| `voyti/roles-create` | `GET`, `POST` | `roles/create` | Create role |
| `voyti/roles-update` | `GET`, `POST` | `roles/update/{name}` | Update role |
| `voyti/roles-delete` | `POST` | `roles/delete/{name}` | Delete role |
| `voyti/rules` | `GET` | `rules` | List rules |
| `voyti/rules-create` | `GET`, `POST` | `rules/create` | Create rule |
| `voyti/rules-update` | `GET`, `POST` | `rules/update/{name}` | Update rule |
| `voyti/rules-delete` | `POST` | `rules/delete/{name}` | Delete rule |

## Testing

```bash
# Unit tests
composer phpunit

# Mutation testing
composer infection

# Static analysis
composer psalm
```

## Credits

Originally based on [2amigos/yii2-usuario](https://github.com/2amigos/yii2-usuario) by 2amigOS.

## License

[MIT](LICENSE)
