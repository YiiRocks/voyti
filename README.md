# Voyti — Yii3 User Management Extension

Highly customizable and extensible user management, authentication, and authorization extension for Yii3.

Ported from [2amigos/yii2-usuario](https://github.com/2amigos/yii2-usuario) and rebuilt for Yii3 with PSR-15 middleware, PSR-11 DI, ActiveRecord entities, FormModel forms, and the `yiisoft/rbac` package.

[![Packagist Version](https://img.shields.io/packagist/v/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/yiirocks/voyti.svg)](https://php.net/)
[![Packagist](https://img.shields.io/packagist/dt/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![GitHub](https://img.shields.io/github/license/yiirocks/voyti.svg)](https://github.com/yiirocks/voyti/blob/master/LICENSE)
[![GitHub Workflow Status](https://img.shields.io/github/workflow/status/yiirocks/voyti/analysis)](https://github.com/yiirocks/voyti/actions)

---

## Features

- **User Management** — Registration, email confirmation, login/logout, password recovery, password expiration
- **Profile Management** — User profiles with gravatar, timezone, social links
- **Social Authentication** — 9 built-in auth clients (Facebook, GitHub, Google, LinkedIn, Twitter, VKontakte, Yandex, Keycloak, Microsoft365)
- **Two-Factor Authentication** — TOTP (authenticator app), email, and SMS 2FA with enforced-per-permission support
- **RBAC Management** — Full admin UI for roles, permissions, and rules with parent-child hierarchy, assignment management, and filtering
- **Session Management** — Session history tracking and termination
- **GDPR Compliance** — Consent management, data export, anonymized deletion with admin notification
- **Password Policies** — Minimum complexity requirements, max age enforcement via middleware
- **Email Change Strategies** — Three modes: insecure (immediate), default (confirm new address), secure (confirm both old and new)
- **REST API** — Optional JSON API for user CRUD
- **CAPTCHA** — Optional reCAPTCHA v2/v3 integration via `yiirocks/recaptcha`
- **i18n** — Built-in translations for English, German, Dutch, and Russian
- **Themed Views** — Bootstrap 5 views shipped by default; mail templates separate and independently overridable

## Requirements

- PHP >= 8.2
- Yii3 packages (yiisoft/db, yiisoft/rbac, yiisoft/view, yiisoft/validator, etc.)

## Installation

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

## Quick Start

### 1. Run migrations

```bash
php yii migrate
```

Five migrations create the `user`, `profile`, `social_account`, `token`, and `session_history` tables with all columns (2FA, GDPR, password expiration, last login IP, etc.) included.

### 2. Configure the module (optional)

Sensible defaults are auto-registered via the [Yii3 config plugin](https://github.com/yiisoft/config) — no manual setup required. To customize, override `ModuleConfig` in your application's `config/params.php`:

```php
use YiiRocks\Voyti\ModuleConfig;

return [
    YiiRocks\Voyti\ModuleConfig::class => new ModuleConfig(
        enableRegistration: true,            // default: true
        enablePasswordRecovery: true,        // default: true
        enableTwoFactorAuthentication: true, // default: false
        recaptchaVersion: 'v3',              // default: null ('v2', 'v3', or null to disable)
        emailChangeStrategy: 1,              // default: 1 (0 = insecure, 1 = default, 2 = secure)
        enableGdprCompliance: true,          // default: false
        maxPasswordAge: 90,                  // default: null (days, null to disable)
        enableRestApi: true,                 // default: false (enables /voyti/api/v1/users)
    ),
];
```

### 3. That's it

Routes, DI bindings, event listeners, and console commands are all auto-registered via the config plugin. The package works out of the box under the `/voyti` URL prefix.

Web routes cover security, registration, recovery, profile, settings, admin, and RBAC. REST API routes (under `/voyti/api/v1`) are enabled when `enableRestApi` is `true`.

Console commands:

| Command | Description |
|---------|-------------|
| `voyti:create` | Create a new user |
| `voyti:delete` | Delete a user |
| `voyti:confirm` | Confirm a user's email |
| `voyti:password` | Change a user's password |

## Configuration Reference

`ModuleConfig` provides 40+ options:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `recaptchaVersion` | `?string` | `null` | `'v2'`, `'v3'`, or `null` to disable |
| `enableSessionHistory` | `bool` | `false` | Track session history |
| `numberSessionHistory` | `int\|false` | `false` | Max sessions to keep per user |
| `timeoutSessionHistory` | `int\|false` | `false` | Session timeout in seconds |
| `enableGdprCompliance` | `bool` | `false` | Enable GDPR features |
| `gdprPrivacyPolicyUrl` | `?string` | `null` | URL to privacy policy |
| `gdprAnonymizePrefix` | `string` | `'GDPR'` | Prefix for anonymized usernames |
| `gdprRequireConsentToAll` | `bool` | `false` | Require consent on all pages |
| `enableTwoFactorAuthentication` | `bool` | `false` | Enable 2FA |
| `twoFactorAuthenticationForcedPermissions` | `array` | `[]` | Permissions that require 2FA |
| `twoFactorAuthenticationCycles` | `int` | `1` | 2FA code generation cycles |
| `enableAutoLogin` | `bool` | `true` | Auto-login after registration |
| `enableRegistration` | `bool` | `true` | Allow new user registration |
| `enableSocialNetworkRegistration` | `bool` | `true` | Allow social network registration |
| `enableEmailConfirmation` | `bool` | `true` | Require email confirmation |
| `generatePasswords` | `bool` | `false` | Auto-generate passwords on registration |
| `allowUnconfirmedEmailLogin` | `bool` | `false` | Allow login without email confirmation |
| `allowPasswordRecovery` | `bool` | `true` | Allow password recovery |
| `allowAccountDelete` | `bool` | `false` | Allow users to delete their account |
| `emailChangeStrategy` | `int` | `1` | 0=insecure, 1=default, 2=secure |
| `rememberLoginLifespan` | `int` | `1209600` | Remember-me duration (seconds) |
| `tokenConfirmationLifespan` | `int` | `86400` | Confirmation token validity |
| `tokenRecoveryLifespan` | `int` | `21600` | Recovery token validity |
| `administrators` | `array` | `[]` | Admin user IDs/usernames |
| `administratorPermissionName` | `?string` | `null` | Permission name for admin access |
| `blowfishCost` | `int` | `10` | Bcrypt cost factor |
| `maxPasswordAge` | `?int` | `null` | Max password age in days |
| `disableIpLogging` | `bool` | `false` | Disable IP address logging |
| `minPasswordRequirements` | `array` | `['lower'=>1,'digit'=>1,'upper'=>1]` | Min character types |
| `enableRestApi` | `bool` | `false` | Enable REST API |
| `adminRestPrefix` | `string` | `'api/v1'` | REST API URL prefix |
| `mailParams` | `array` | (see below) | Mail from address and subjects |

## Views

### Web Views

Web views are in `src/resources/views/bootstrap5/` and use the `@voytiViews` alias. They can be overridden via the Yii3 View theme `pathMap`:

```php
// config/params.php
'yiisoft/view' => [
    'theme' => [
        'pathMap' => [
            '@voytiViews' => [
                '/path/to/your/custom/views',
                '@voyti/resources/views/bootstrap5',  // fallback
            ],
        ],
    ],
],
```

To add support for another CSS framework (e.g. Tailwind), create a `src/resources/views/tailwind/` directory and set:

```php
'@voytiViews' => ['@voyti/resources/views/tailwind'],
```

### Mail Views

Mail templates are in `src/resources/mail/` and use the `@voytiMail` alias — separate from web views so they can be overridden independently:

```php
'yiisoft/view' => [
    'theme' => [
        'pathMap' => [
            '@voytiMail' => [
                '/path/to/your/custom/mail',
                '@voyti/resources/mail',  // fallback
            ],
        ],
    ],
],
```

## Middleware

The extension ships three PSR-15 middleware classes for access control:

| Middleware | Description |
|-----------|-------------|
| `AccessRuleMiddleware` | Redirects non-admin users; checks `administratorPermissionName` |
| `PasswordAgeEnforceMiddleware` | Redirects to password change when `maxPasswordAge` is exceeded |
| `TwoFactorAuthenticationEnforceMiddleware` | Redirects to 2FA setup when required permissions are assigned |

Register them in your application's middleware pipeline as needed.

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

## Social Authentication

Nine auth clients are included. Each implements the auth client interface and maps provider attributes to the `SocialNetworkAccount` entity:

- Facebook, GitHub, Google, Keycloak, LinkedIn, Microsoft365, Twitter, VKontakte, Yandex

The `SocialNetworkAuthenticateService` handles account lookup, creation, and user login. The `SocialNetworkAccountConnectService` links a social account to an existing user.

## Testing

```bash
# Unit tests (264 tests, 3509 assertions)
composer phpunit

# Mutation testing (275 mutants, 100% MSI)
composer infection

# Code style
composer php-cs-fixer

# Static analysis
composer psalm

# Mess detector
composer phpmd
```

## Project Structure

```
src/
├── AuthClient/          9 social auth clients
├── Command/             4 console commands
├── Controller/          11 web controllers + 1 API controller
├── Entity/              5 ActiveRecord entities (User, Profile, Token, SocialNetworkAccount, SessionHistory)
├── Event/               11 event classes
├── Factory/             TokenFactory, MailFactory
├── Form/                9 form models (Login, Registration, Recovery, Resend, Settings, Rule, Permission, Role, Assignment, GdprDelete)
├── Helper/              5 helpers (Auth, Gravatar, Recaptcha, Security, Timezone)
├── Listener/            4 event listeners
├── Middleware/          3 PSR-15 middleware
├── Migration/           5 table-creation migrations
├── Repository/          6 repositories (User, Profile, Token, SocialNetworkAccount, SessionHistory + RepositoryTrait)
├── Service/             22 services + 5 session history services
├── Strategy/            3 email-change strategies + factory + interface
├── Validator/           5 validators
├── Widget/              4 widgets
├── resources/
│   ├── mail/            5 mail templates (independently overridable)
│   ├── messages/        4 locales (en, de, nl, ru)
│   └── views/
│       └── bootstrap5/  33 web views
└── ModuleConfig.php     40+ configuration options
```

## Credits

Originally based on [2amigos/yii2-usuario](https://github.com/2amigos/yii2-usuario) by 2amigOS!, which itself was inspired by [Dektrium](https://dektrium.com/)'s Yii2 User and RBAC modules.

## License

[MIT](LICENSE)
