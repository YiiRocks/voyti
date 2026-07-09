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
[![GitHub](https://img.shields.io/github/license/yiirocks/voyti.svg)](https://github.com/yiirocks/voyti/blob/main/LICENSE.md)
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
8. [Events & Listeners](#events--listeners)
9. [Testing](#testing)

## Features

- **User Management** — Registration, email confirmation, login/logout with remember-me, password recovery, password expiration
- **Profile Management** — User profiles with gravatar, timezone, bio, and a personal website link
- **Social Authentication** — Various built-in auth clients as [listed below](#social-authentication)
- **Two-Factor Authentication** — TOTP (authenticator app) and email 2FA with enforced-per-permission support
- **RBAC Management** — Full admin UI for roles, permissions, and rules with parent-child hierarchy, assignment management, and filtering
- **Identity Switching** — Admins can temporarily switch into another user's identity for support or debugging, then restore their own session with one click
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

Pull the `voyti-routes` config group into your router definition. The example below mounts them under a `/user/` prefix as their own group, alongside your app's own routes — change the prefix as needed:

```php
use Yiisoft\Config\Config;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Router\Group;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Session\SessionMiddleware;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\TwoFactorAuthenticationEnforceMiddleware;

/** @var Config $config */

return [
    RouteCollectionInterface::class => [
        'class' => RouteCollection::class,
        '__construct()' => [
            'collector' => DynamicReference::to(
                static fn() => (new RouteCollector())
                    ->addRoute(
                        Group::create('/')
                            ->middleware(
                                SessionMiddleware::class,
                                // Site-wide enforcement (see "Site-wide enforcement" below).
                                // Safe to scope to this group only: accountSettingsRoute lives
                                // in the separate voyti-routes group below, so redirecting there
                                // takes the request out of this group and can't loop.
                                PasswordAgeEnforceMiddleware::class,
                                TwoFactorAuthenticationEnforceMiddleware::class,
                            )
                            ->routes(...$config->get('routes')), // your own app routes
                        Group::create('/user/')
                            ->routes(...$config->get('voyti-routes')),
                    )
            ),
        ],
    ],
];
```

`voyti-routes` already wraps itself with `SessionMiddleware` and `CsrfMiddleware` internally (see `config/routes.php`), so the second group doesn't need to repeat them. `PasswordAgeEnforceMiddleware` is also already applied inside `voyti-routes` automatically when `enablePasswordExpiration` is `true`, so adding it to your own group above only extends that protection to your app's own pages — it isn't duplicating work. `TwoFactorAuthenticationEnforceMiddleware`, however, is never auto-applied by the extension itself, so add it wherever you want that enforcement.

When `enableRestApi` is `true`, the API routes are mounted under `adminRestPrefix . '/v1/'` and expose user CRUD endpoints. The version segment (`v1`) always matches the shipped `Controller\api\v1\AdminController` and isn't configurable — `adminRestPrefix` only controls the base path in front of it (e.g. `api` → `api/v1/users`).

The privacy/GDPR routes (`settings/privacy`, `settings/privacy/gdpr-consent`, `settings/privacy/export`, `settings/privacy/anonymize`, `settings/privacy/delete`) and the two-factor routes (`settings/two-factor`, `settings/two-factor/enable`, `settings/two-factor/disable`) are likewise only registered when their governing config flag (`enableGdprCompliance`, `allowAccountDelete`, and/or `enableTwoFactorAuthentication`) is `true` — see the route table below. When a flag is off, the corresponding route doesn't exist at all, so a request to it falls through to the host application's own router-level not-found handling.

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

Below are all top-level `yiirocks/voyti` options, followed by the nested `socialNetworkClients` options.

### General

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `appName` | `string` | `'Voyti'` | Application name — used as TOTP issuer in 2FA QR codes and `{app}` placeholder in mail subjects |

### Authentication & Registration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `loginRoute` | `string` | `'voyti/login'` | Login route name |
| `accountSettingsRoute` | `string` | `'voyti/settings-account'` | Route for account settings redirects |
| `homeRoute` | `string` | `'home'` | Route to redirect to after a successful login (password, 2FA, or social) or logout. Must be a route registered by the host app — an unregistered route name throws a `LogicException` naming the misconfigured option, rather than a bare router exception |
| `enableRegistration` | `bool` | `true` | Allow new user registration |
| `enableSocialNetworkRegistration` | `bool` | `true` | Allow social network registration |
| `socialNetworkClients` | `array` | `[]` | OAuth client IDs, secrets, and provider-specific options |
| `enableEmailConfirmation` | `bool` | `true` | Require email confirmation |
| `generatePasswords` | `bool` | `false` | Auto-generate passwords on registration |
| `allowPasswordRecovery` | `bool` | `true` | Allow password recovery |
| `allowAdminPasswordRecovery` | `bool` | `true` | Allow admin-initiated password recovery |
| `allowAccountDelete` | `bool` | `false` | Allow users to delete their account |
| `emailChangeStrategy` | `int` | `1` | 0=insecure, 1=default, 2=secure |
| `rememberLoginLifespan` | `int` | `2592000` | Remember-me cookie lifetime and idle auth timeout in seconds |
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
| `gdprExportProperties` | `array` | `['email', 'username', 'userProfile.public_email', 'userProfile.name', 'userProfile.gravatar_email', 'userProfile.location', 'userProfile.website', 'userProfile.bio', 'userSessionHistory', 'userSocialAccount']` | Properties included in the data export (JSON). `userSessionHistory` exports each login's `ip`, `user_agent`, `created_at`, `updated_at` (the internal `session_id` is excluded); `userSocialAccount` exports each linked account's `provider`, `username`, `email`, `created_at`, and `data` (the decoded provider profile payload). The OAuth `code` field is excluded — it's a one-time linking secret, not user data |

```php
'yiirocks/voyti' => [
    'enableGdprCompliance' => true,
    'gdprAnonymizePrefix' => 'ANON-',
],
```

### Session & Security

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableSessionHistory` | `bool` | `true` | Track session history |
| `numberSessionHistory` | `int\|false` | `50` | Max sessions to keep per user |
| `disableIpLogging` | `bool` | `false` | Disable IP address logging |
| `enablePasswordExpiration` | `bool` | `false` | Enable password expiration |
| `maxPasswordAge` | `?int` | `null` | Max password age in days |
| `enablePasswordComplexity` | `bool` | `false` | Require passwords to contain an uppercase letter, a lowercase letter, a digit, and a special character |
| `administratorPermissionName` | `?string` | `'admin'` | Permission/role name granting admin access |
| `profileVisibility` | `int` | `2` | Profile visibility: `0` = owner only, `1` = owner + admins, `2` = any authenticated user, `3` = public |

### Views & Mail

| Option | Type | Default | Description |
|--------|------|--------|--------------|
| `viewPath` | `string` | `__DIR__ . '/../resources/views/bootstrap5'` | Base path for web templates |
| `mailPath` | `string` | `__DIR__ . '/../resources/mail'` | Base path for mail templates |

### REST API

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableRestApi` | `bool` | `false` | Enable REST API |
| `adminRestPrefix` | `string` | `'api'` | REST API base URL prefix |
| `apiTokenLifespan` | `int\|null` | `null` | API token lifetime in seconds; `null` means tokens never expire |

The REST API authenticates via a Bearer token, not the web session/CSRF cookie — issue one with `voyti:api-token:generate` (see [Console commands](#console-commands)) and send it as `Authorization: Bearer <token>`. `AccessRuleMiddleware` still applies afterwards to enforce `administratorPermissionName`.

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
| `voyti:api-token:generate` | Generate a REST API access token for a user (printed once) |
| `voyti:api-token:revoke` | Revoke all REST API access tokens for a user |

## Middleware

The extension ships four PSR-15 middleware classes for access control:

| Middleware | Description | Auto-registered on the extension's own routes? |
|-----------|-------------|-----------|
| `AccessRuleMiddleware` | Redirects guests to `loginRoute`; checks `administratorPermissionName` for admin access | Yes — on `admin/*`, `permissions/*`, `roles/*`, `rules/*`, and the REST API group |
| `ApiTokenAuthenticationMiddleware` | Resolves the `Authorization: Bearer <token>` header to a user for that request only (no session); returns `401` if missing/invalid | Yes — on the REST API group, ahead of `AccessRuleMiddleware`, in place of the session cookie |
| `PasswordAgeEnforceMiddleware` | Redirects to `accountSettingsRoute` when `maxPasswordAge` is exceeded | Yes, when `enablePasswordExpiration` is `true` — on the extension's whole web route group |
| `TwoFactorAuthenticationEnforceMiddleware` | Redirects to `accountSettingsRoute` when required permissions are assigned but 2FA isn't enabled | No |

The redirect targets (`loginRoute` and `accountSettingsRoute`) are configurable via `ModuleConfig`, so you can map them to your own route structure.

### Site-wide enforcement

The auto-registration above only covers routes *this extension defines* (`voyti/login`, `voyti/settings`, `voyti/admin`, etc.) — it has no way to reach routes your host application defines itself, since `config/routes.php` can only attach middleware to the route groups it builds. If a user with an expired password or missing 2FA navigates to your app's own dashboard, home page, or any other route outside this extension, nothing stops them.

To actually enforce "the user must fix this before doing anything else" across your own pages too, add `PasswordAgeEnforceMiddleware` and/or `TwoFactorAuthenticationEnforceMiddleware` to the `Group` that wraps your app's own routes — see the [Register routes](#3-register-routes) example above, which applies both alongside `SessionMiddleware`. Add any other middleware your own routes need (CSRF protection, etc.) — the example only lists what's required for login/session state to work. Keep the enforcement middlewares scoped to your own route group (as in that example) rather than the `voyti-routes` group: `accountSettingsRoute` lives inside `voyti-routes`, and `TwoFactorAuthenticationEnforceMiddleware` has no built-in exemption for it, so wrapping `voyti-routes` with it would redirect a user straight back into a loop the moment they try to reach the settings page to actually fix the problem.

If your app instead configures middleware at a level above routing entirely (e.g. a global pipeline in your app skeleton's runner config), the same two classes can go there instead — just place them after session middleware so `CurrentUser` is resolvable, and keep the same caveat in mind for whatever route ends up serving `accountSettingsRoute`.

## RBAC

Built on [`yiisoft/rbac`](https://github.com/yiisoft/rbac). The extension provides:

- **Admin UI** for managing permissions, roles, and rules (create, update, delete, filter)
- **Assignment management** — assign/revoke roles and permissions per user from the admin panel
- **Parent-child hierarchy** — roles can have child permissions/roles
- **Rule management** — register and manage custom `RuleInterface` classes

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
| `voyti/settings-networks-disconnect` | `POST` | `settings/networks/disconnect/{id}` | Disconnect social account |
| `voyti/settings-privacy` | `GET` | `settings/privacy` | Privacy settings. Only registered when `enableGdprCompliance` or `allowAccountDelete` is `true` |
| `voyti/settings-privacy-gdpr-consent` | `GET`, `POST` | `settings/privacy/gdpr-consent` | GDPR consent. Only registered when `enableGdprCompliance` is `true` |
| `voyti/settings-privacy-export` | `GET` | `settings/privacy/export` | Export user data. Only registered when `enableGdprCompliance` is `true` |
| `voyti/settings-privacy-anonymize` | `GET`, `POST` | `settings/privacy/anonymize` | Anonymize account (blanks email/username, blocks login; row is kept). Only registered when `enableGdprCompliance` is `true` |
| `voyti/settings-privacy-delete` | `GET`, `POST` | `settings/privacy/delete` | Account deletion (hard delete). Only registered when `allowAccountDelete` is `true` |
| `voyti/settings-two-factor` | `GET`, `POST` | `settings/two-factor` | Two-factor setup. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/settings-two-factor-enable` | `POST` | `settings/two-factor/enable` | Enable 2FA. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/settings-two-factor-disable` | `POST` | `settings/two-factor/disable` | Disable 2FA. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/settings-confirm` | `GET` | `settings/confirm/{code}` | Confirm account changes |
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

## Events & Listeners

Voyti dispatches events at key points in the user lifecycle, allowing your application to react, log, or extend behaviour. Each event carries a `const string` name to distinguish before/after variants. Attach your own listeners through the Yii3 event dispatcher configuration.

### Events with default listeners

| Event | Trigger | Default behavior |
|-------|---------|-------------------|
| `AfterLoginEvent` | User logs in | Triggers password expiration check and session history tracking |
| `AfterRegisterEvent` | New user registration | Sends admin notification email |

### Additional events (no default listeners)

Dispatched by the library, but nothing consumes them by default — attach your own listener via the event dispatcher configuration if you need to react to them.

- **UserEvent** — Variants: create, delete, block/unblock, confirmation, account/profile update, switch identity, logout
- **UserProfileEvent** — Dispatched when a user updates their profile
- **GdprEvent** — Specifically: delete operation
- **ResetPasswordEvent** — Password reset flow
- **SessionEvent** — Dispatched with type `SESSION_CREATED` on login, and with type `SESSION_TERMINATED` whenever a user's sessions are terminated (account deletion, GDPR anonymization, or being blocked). The `SESSION_UPDATED` type is defined but not currently dispatched.

## Testing

```bash
# Unit tests
composer phpunit

# Mutation testing
composer infection

# Static analysis
composer psalm
```

## License

[MIT](LICENSE.md)
