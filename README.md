# Voyti — Yii3 User Management Extension

> **войти**  
> ***/vɐjˈtʲi/***  
> *verb*
>
> "to enter" or "to log in"

Highly customizable and extensible user management, authentication, and authorization extension for Yii3.

Ported from [2amigos/yii2-usuario](https://github.com/2amigos/yii2-usuario) and rebuilt for Yii3 with PSR-15 middleware, PSR-11 DI, ActiveRecord models, FormModel forms, and RBAC.

[![Packagist Version](https://img.shields.io/packagist/v/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/yiirocks/voyti.svg)](https://php.net/)
[![Packagist](https://img.shields.io/packagist/dt/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![GitHub](https://img.shields.io/github/license/yiirocks/voyti.svg)](https://github.com/yiirocks/voyti/blob/main/LICENSE.md)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/yiirocks/voyti/build.yml?branch=main)](https://github.com/yiirocks/voyti/actions)

Stats for Nerds

[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Fcoverage.json)](https://github.com/yiirocks/voyti/tree/badges)
[![MSI](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Fmsi.json)](https://github.com/yiirocks/voyti/tree/badges)
[![Tests](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Ftests.json)](https://github.com/yiirocks/voyti/tree/badges)
[![Assertions](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Fassertions.json)](https://github.com/yiirocks/voyti/tree/badges)

---

## Table of Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Quick Start](#quick-start)
4. [Configuration](#configuration)
5. [Social Authentication](#social-authentication)
6. [Console commands](#console-commands)
7. [Middleware](#middleware)
8. [RBAC](#rbac)
9. [Routes](#routes)
10. [Events & Listeners](#events--listeners)
11. [Testing](#testing)

## Features

- **User Management** — Registration, email confirmation, login/logout with remember-me, password recovery, password expiration
- **Profile Management** — User profiles with gravatar, timezone, bio, and a personal website link
- **Social Authentication** — Various built-in auth clients
- **Two-Factor Authentication** — TOTP (authenticator app) and email 2FA with enforced-per-permission support, plus one-time backup codes for account recovery
- **RBAC Management** — Full admin UI for roles, permissions, and rules with parent-child hierarchy, assignment management, and filtering
- **Identity Switching** — Admins can temporarily switch into another user's identity for support or debugging, then restore their own session with one click
- **Session Management** — Session tracking and termination
- **GDPR Compliance** — Consent management, data export, anonymized deletion with admin notification
- **Password Policies** — Minimum complexity requirements, max age enforcement via middleware
- **Email Change Confirmation** — Three modes: immediate, confirm new address, confirm both old and new
- **REST API** — Optional JSON API for user CRUD
- **CAPTCHA** — Optional reCAPTCHA v2/v3 integration via `yiirocks/recaptcha`
- **i18n** — Built-in translations for multiple languages
- **Themed Views** — Bootstrap 5 views shipped by default; mail templates separate and independently overridable

## Requirements

- PHP >= 8.3
- `ext-intl`
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
`user_token`, `user_sessions`, `user_backup_code`, `user_password_history`,
and `audit_log` tables with all columns (2FA, GDPR, password expiration, last
login IP, etc.) included. `config/params-console.php` also registers
`yiisoft/rbac-db`'s own item/assignment migrations, so `./yii migrate:up`
creates the RBAC tables too — no separate step needed.

If the `user` table is still empty after these migrations run, a default
admin account is seeded automatically: username `admin`, email
`admin@example.com`, and a random 20-character password printed to the
console — copy it immediately, it isn't stored anywhere else. The account is
assigned the `administrator` role, which is granted the `administratorPermissionName`
permission (`voyti-admin-dashboard` by default) needed to reach the admin
dashboard. **Change this password immediately after first login.** If the
`user` table already has rows (e.g. re-running migrations on an existing
database), seeding is skipped entirely.

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
use YiiRocks\Voyti\Middleware\VoytiMiddleware;

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
                                SessionMiddleware::class,                 // required for site-wide session support
                                VoytiMiddleware::class,                   // see "Site-wide enforcement" below
                            )
                            ->routes(...$config->get('routes')),          // your own app routes
                        Group::create('/user/')
                            ->routes(...$config->get('voyti-routes')),
                    )
            ),
        ],
    ],
];
```

`voyti-routes` already wraps itself with its own required middleware (see `config/routes.php`), so the second group above doesn't repeat any of it, and adding `VoytiMiddleware` to your own group only extends that same coverage to your app's pages.

When `enableRestApi` is `true`, the API routes are mounted under `adminRestPrefix . '/v1/'` and expose user CRUD endpoints.

The privacy/GDPR routes (`settings/privacy/`, `settings/privacy/gdpr-consent`, `settings/privacy/export`, `settings/privacy/anonymize`, `settings/privacy/delete`) and the two-factor routes (`settings/two-factor/`, `settings/two-factor-google/enable`, `settings/two-factor/disable/`) are likewise only registered when their governing config flag (`enableGdprCompliance`, `allowAccountDelete`, and/or `enableTwoFactorAuthentication`) is `true` — see the route table below. When a flag is off, the corresponding route doesn't exist at all, so a request to it falls through to the host application's own router-level not-found handling.

### 4. Done

DI bindings, event listeners, and console commands are auto-registered via the
[Yii3 config plugin](https://github.com/yiisoft/config). No manual wiring needed.

## Configuration

Override Voyti params in your app's `config/params.php` using the
`yiirocks/voyti` key:

```php
use YiiRocks\Voyti\Enum\RecaptchaVersion;

return [
    'yiirocks/voyti' => [
        'appName' => 'My Project',
        'recaptchaVersion' => RecaptchaVersion::V3,
    ],
];
```

Below are all top-level `yiirocks/voyti` options, followed by the nested `socialNetworkClients` options.

### General

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `appName` | `string` | `'Voyti'` | Application name — used as TOTP issuer in 2FA QR codes and `{app}` placeholder in mail subjects |
| `homeRoute` | `string` | `'home'` | Route to redirect to after a successful login (password, 2FA, or social) or logout. Must be a route registered by the host app — an unregistered route name throws a `LogicException` naming the misconfigured option, rather than a bare router exception |

### Authentication & Registration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableRegistration` | `bool` | `true` | Allow new user registration |
| `enableSocialNetworkRegistration` | `bool` | `true` | Allow social network registration |
| `socialNetworkClients` | `array` | `[]` | OAuth client IDs, secrets, and provider-specific options |
| `enableEmailConfirmation` | `bool` | `true` | Require email confirmation |
| `allowPasswordRecovery` | `bool` | `true` | Allow password recovery |
| `allowAdminPasswordRecovery` | `bool` | `false` | Allow admin-initiated password recovery |
| `allowAccountDelete` | `bool` | `false` | Allow users to delete their account |
| `emailChangeConfirmation` | `EmailChangeConfirmation` | `EmailChangeConfirmation::NEW` | `NONE` (change immediately), `NEW` (confirm new address only), or `BOTH` (confirm both old and new addresses) |
| `rememberLoginLifespan` | `int` | `2592000` | Remember-me cookie lifetime and idle auth timeout in seconds |
| `tokenConfirmationLifespan` | `int` | `86400` | Confirmation token validity |
| `tokenRecoveryLifespan` | `int` | `21600` | Recovery token validity |
| `enableSwitchIdentities` | `bool` | `true` | Allow admin to switch user identities |
| `mailAdminOnRegister` | `?string` | `null` | Email notified on new registration |
| `recaptchaVersion` | `?RecaptchaVersion` | `null` | `RecaptchaVersion::V2`, `RecaptchaVersion::V3`, or `null` to disable |

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
| `gdprExportProperties` | `array` | `['email', 'username', 'userProfile.public_email', 'userProfile.name', 'userProfile.gravatar_email', 'userProfile.location', 'userProfile.website', 'userProfile.bio', 'userProfile.birthday', 'userSessions', 'userSocialAccount']` | Properties included in the data export (JSON). `userSessions` exports each login's `ip`, `user_agent`, `created_at`, `updated_at` (the internal `session_id` is excluded); `userSocialAccount` exports each linked account's `provider`, `username`, `email`, `created_at`, and `data` (the decoded provider profile payload). The OAuth `code` field is excluded — it's a one-time linking secret, not user data |

```php
'yiirocks/voyti' => [
    'enableGdprCompliance' => true,
    'gdprAnonymizePrefix' => 'ANON-',
],
```

### Session & Security

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `disableIpLogging` | `bool` | `false` | Disable IP address logging |
| `enablePasswordExpiration` | `bool` | `false` | Enable password expiration |
| `maxPasswordAge` | `?int` | `null` | Max password age in days |
| `enablePasswordComplexity` | `bool` | `false` | Require passwords to contain an uppercase letter, a lowercase letter, a digit, and a special character |
| `passwordHistoryLimit` | `int` | `10` | Number of previous passwords remembered per user to prevent reuse. Only enforced when `enablePasswordExpiration` is `true` |
| `administratorPermissionName` | `string` | `'voyti-admin-dashboard'` | Permission name granting admin access |
| `profileVisibility` | `ProfileVisibility` | `ProfileVisibility::USERS` | Profile visibility: `OWNER` = owner only, `ADMIN` = owner + admins, `USERS` = any authenticated user, `PUBLIC` = public |
| `enableAuditLog` | `bool` | `true` | Record admin actions (RBAC and user management changes) to the `audit_log` table, viewable at `admin/audit-log/` |

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
| `redirectUri` | `string` | no | generated callback URL | Overrides the callback URL; otherwise Voyti uses the absolute route URL for `voyti/session-auth` |
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
2. `settings/networks/` lists connected providers and renders connect buttons for the remaining configured providers.
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

The extension ships seven PSR-15 middleware classes for session handling and access control:

| Middleware | Description | Auto-registered on the extension's own routes? |
|-----------|-------------|-----------|
| `AccessRuleMiddleware` | Redirects guests to the login page (`voyti/session-login`); checks `administratorPermissionName` for admin access | Yes — on `admin/*` (users and RBAC management) and the REST API group |
| `ApiTokenAuthenticationMiddleware` | Resolves the `Authorization: Bearer <token>` header to a user for that request only (no session); returns `401` if missing/invalid | Yes — on the REST API group, ahead of `AccessRuleMiddleware`, in place of the session cookie |
| `RememberMeMiddleware` | Logs a guest back in from the `autoLogin` remember-me cookie, then writes the cookie back onto the response — either the immediate reissue after a session rotation or the periodic sliding-expiration refresh. Must run after session middleware and before the enforcement middleware below, since those need `CurrentUser` already resolved | Yes |
| `SessionRevocationEnforceMiddleware` | Logs out and redirects to the login page (`voyti/session-login`) when the current session's `user_sessions` row is gone — i.e. it was terminated from the sessions list (self-service or admin) on another request. Without this, terminating a session only removed the row; the browser that owned it stayed logged in until its PHP session expired on its own. Otherwise touches the row's `updated_at` on every request, so the sessions list can show "last seen" activity per device. | Yes |
| `PasswordAgeEnforceMiddleware` | Redirects to the account settings page (`voyti/account-update`) when `maxPasswordAge` is exceeded | Yes, when `enablePasswordExpiration` is `true` — on the extension's whole web route group |
| `TwoFactorAuthenticationEnforceMiddleware` | Redirects to the account settings page (`voyti/account-update`) when required permissions are assigned but 2FA isn't enabled | No |
| `VoytiMiddleware` | Convenience wrapper that chains `RememberMeMiddleware`, `SessionRevocationEnforceMiddleware`, `PasswordAgeEnforceMiddleware`, and `TwoFactorAuthenticationEnforceMiddleware` in a single middleware entry — add this to your app's route group instead of the four individual ones (see [Site-wide enforcement](#site-wide-enforcement)) | No |

### Site-wide enforcement

The auto-registration above only covers routes *this extension defines* — `config/routes.php` can't attach middleware to routes your host app defines itself. Without it, a user with an expired password, missing 2FA, or a revoked session can still browse your app's own dashboard, home page, or any other route outside this extension — and a guest with a valid remember-me cookie won't be logged back in there either.

Add `VoytiMiddleware` to the `Group` wrapping your app's own routes — see the [Register routes](#3-register-routes) example above — or to a global middleware pipeline above routing if your app has one; make sure to place it after `SessionMiddleware` so `CurrentUser` is resolvable. Each sub-middleware checks its own feature flag, so disabled features are no-ops. Keep it scoped to your own routes, not the `voyti-routes` group.

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
| `voyti/session-login` | `GET`, `POST` | `login` | User login |
| `voyti/session-logout` | `GET`, `POST` | `logout` | User logout |
| `voyti/session-confirm` | `GET`, `POST` | `confirm` | Two-factor confirmation step |
| `voyti/session-auth` | `GET` | `auth/{provider}` | Social auth callback |
| `voyti/registration-register` | `GET`, `POST` | `register` | New user registration |
| `voyti/registration-confirm` | `GET`, `POST` | `confirm/{id}/{code}` | Email confirmation link |
| `voyti/registration-resend` | `GET`, `POST` | `resend` | Resend confirmation email |
| `voyti/registration-connect` | `GET` | `connect/{code}` | Social registration link |
| `voyti/password-reset-request` | `GET`, `POST` | `forgot` | Password recovery request |
| `voyti/password-reset-confirm` | `GET`, `POST` | `recover/{id}/{code}` | Password reset |
| `voyti/profile` | `GET` | `profile/{id}` | Public user profile |
| `voyti/profile-update` | `GET`, `POST` | `settings/` | Profile settings |
| `voyti/account-update` | `GET`, `POST` | `settings/account` | Account settings |
| `voyti/account-confirm` | `GET` | `settings/confirm/{code}` | Confirm account changes |
| `voyti/social-network` | `GET` | `settings/networks/` | Linked social networks |
| `voyti/social-network-delete` | `POST` | `settings/networks/disconnect/{id}` | Disconnect social account |
| `voyti/account-sessions` | `GET` | `settings/sessions/` | Self-service session/device list, current device highlighted |
| `voyti/account-sessions-terminate` | `POST` | `settings/sessions/terminate/{sessionId}` | Terminate one of the current user's own sessions |
| `voyti/privacy` | `GET` | `settings/privacy/` | Privacy settings. Only registered when `enableGdprCompliance` or `allowAccountDelete` is `true` |
| `voyti/privacy-gdpr-consent` | `GET`, `POST` | `settings/privacy/gdpr-consent` | GDPR consent. Only registered when `enableGdprCompliance` is `true` |
| `voyti/privacy-export` | `GET` | `settings/privacy/export` | Export user data. Only registered when `enableGdprCompliance` is `true` |
| `voyti/privacy-anonymize` | `GET`, `POST` | `settings/privacy/anonymize` | Anonymize account (blanks email/username, blocks login; row is kept). Only registered when `enableGdprCompliance` is `true` |
| `voyti/privacy-delete` | `GET`, `POST` | `settings/privacy/delete` | Account deletion (hard delete). Only registered when `allowAccountDelete` is `true` |
| `voyti/two-factor` | `GET`, `POST` | `settings/two-factor/` | Two-factor status/entry point. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-google` | `GET` | `settings/two-factor-google/` | Google Authenticator setup page (method-selector buttons + QR/secret). Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-email` | `GET` | `settings/two-factor-email/` | Email 2FA setup page (method-selector buttons + confirm/send screen). Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-enable` | `POST` | `settings/two-factor-google/enable` | Enable 2FA - shared by both the Google Authenticator and email code-entry forms. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-disable` | `POST` | `settings/two-factor/disable/` | Disable 2FA. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-disable-send-code` | `POST` | `settings/two-factor/disable/send-code` | Send the disable-2FA one-time code. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-renew` | `POST` | `settings/two-factor-google/renew` | Regenerate the Google Authenticator secret/QR code via AJAX. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-send-email-code` | `POST` | `settings/two-factor-email/send-code` | Send the email 2FA one-time code after explicit confirmation. Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/two-factor-regenerate-backup-codes` | `POST` | `settings/two-factor/backup-codes/regenerate` | Invalidate existing backup codes and generate a fresh set (requires re-verifying the current 2FA method). Only registered when `enableTwoFactorAuthentication` is `true` |
| `voyti/admin` | `GET` | `admin/` | Redirects to the admin user dashboard |
| `voyti/admin-users` | `GET` | `admin/users/` | User dashboard |
| `voyti/admin-users-create` | `GET`, `POST` | `admin/users/create` | Create user |
| `voyti/admin-users-update` | `GET`, `POST` | `admin/users/update/{id}` | Update user |
| `voyti/admin-users-update-profile` | `GET`, `POST` | `admin/users/update-profile/{id}` | Update user profile |
| `voyti/admin-users-show` | `GET` | `admin/users/info/{id}` | User details |
| `voyti/admin-users-confirm` | `POST` | `admin/users/confirm/{id}` | Confirm user |
| `voyti/admin-users-delete` | `POST` | `admin/users/delete/{id}` | Delete user |
| `voyti/admin-users-block` | `POST` | `admin/users/block/{id}` | Block user |
| `voyti/admin-users-switch-identity` | `POST` | `admin/users/switch-identity/{id}` | Switch identity |
| `voyti/admin-users-switch-identity-restore` | `POST` | `admin/users/switch-identity/restore` | Restore identity after impersonating |
| `voyti/admin-users-password-reset` | `POST` | `admin/users/password-reset/{id}` | Send password reset |
| `voyti/admin-users-force-password-change` | `POST` | `admin/users/force-password-change/{id}` | Force password change |
| `voyti/admin-users-assignments` | `GET`, `POST` | `admin/users/assignments/{id}` | Manage RBAC assignments |
| `voyti/admin-users-sessions` | `GET` | `admin/users/sessions/{id}` | Session management |
| `voyti/admin-users-terminate-sessions` | `POST` | `admin/users/terminate-sessions/{id}` | Terminate sessions |
| `voyti/admin-rbac-permissions` | `GET` | `admin/rbac/permissions/` | List permissions |
| `voyti/admin-rbac-permissions-create` | `GET`, `POST` | `admin/rbac/permissions/create` | Create permission |
| `voyti/admin-rbac-permissions-update` | `GET`, `POST` | `admin/rbac/permissions/update/{name}` | Update permission |
| `voyti/admin-rbac-permissions-delete` | `POST` | `admin/rbac/permissions/delete/{name}` | Delete permission |
| `voyti/admin-rbac-roles` | `GET` | `admin/rbac/roles/` | List roles |
| `voyti/admin-rbac-roles-create` | `GET`, `POST` | `admin/rbac/roles/create` | Create role |
| `voyti/admin-rbac-roles-update` | `GET`, `POST` | `admin/rbac/roles/update/{name}` | Update role |
| `voyti/admin-rbac-roles-delete` | `POST` | `admin/rbac/roles/delete/{name}` | Delete role |
| `voyti/admin-rbac-rules` | `GET` | `admin/rbac/rules/` | List rules |
| `voyti/admin-rbac-rules-create` | `GET`, `POST` | `admin/rbac/rules/create` | Create rule |
| `voyti/admin-rbac-rules-update` | `GET`, `POST` | `admin/rbac/rules/update/{name}` | Update rule |
| `voyti/admin-rbac-rules-delete` | `POST` | `admin/rbac/rules/delete/{name}` | Delete rule |
| `voyti/admin-audit-log` | `GET` | `admin/audit-log/` | Audit log of admin actions (RBAC and user management changes). Populated when `enableAuditLog` is `true` |

The REST API routes below live in a separate route group mounted at `adminRestPrefix` and are only registered when `enableRestApi` is `true` — see [REST API](#rest-api).

| Route name | Method | Path | Purpose |
|------------|--------|------|---------|
| `voyti/api-openapi` | `GET` | `openapi.json` | OpenAPI 3.1 spec (JSON). Public, so tooling (Swagger UI, codegen) can fetch it without a Bearer token. |
| `voyti/api-v1-users-index` | `GET` | `v1/users` | List users |
| `voyti/api-v1-users-view` | `GET` | `v1/users/{id}` | View a user |
| `voyti/api-v1-users-create` | `POST` | `v1/users` | Create a user |
| `voyti/api-v1-users-update` | `PATCH` | `v1/users/{id}` | Update a user |
| `voyti/api-v1-users-delete` | `DELETE` | `v1/users/{id}` | Delete a user |

## Events & Listeners

Voyti dispatches events at key points in the user lifecycle, allowing your application to react, log, or extend behaviour. Each event carries a `const string` name to distinguish before/after variants. Attach your own listeners through the Yii3 event dispatcher configuration.

### Events with default listeners

| Event | Trigger | Default behavior |
|-------|---------|-------------------|
| `AfterLoginEvent` | User logs in | Triggers password expiration check and session tracking |
| `AfterRegisterEvent` | New user registration | Sends admin notification email |

### Additional events (no default listeners)

Dispatched by the library, but nothing consumes them by default — attach your own listener via the event dispatcher configuration if you need to react to them.

- **UserEvent** — Carries a `getType()` discriminator: `UserEvent::CREATE`, `BLOCK`, `UNBLOCK`, `CONFIRM`, `SWITCH_IDENTITY`, `RESTORE_IDENTITY`, `PASSWORD_RESET`, or `DELETE`
- **UserProfileEvent** — Dispatched when a user updates their profile
- **GdprEvent** — Dispatched after a user's account is anonymized (not on hard delete, which fires `UserEvent` with type `UserEvent::DELETE`)
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

# Code style fixer
composer php-cs-fixer
```

## License

[MIT](LICENSE.md)
