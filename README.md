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

- PHP 8.3+
- ext-intl
- Various yiisoft packages (automatic installation via composer)

## Installation

The package could be installed via composer:

```bash
composer require yiirocks/voyti
```

## Documentation

Installation steps, the full configuration reference, routes, middleware, social auth setup, RBAC, console
commands, and events are all covered at [Yii.rocks](https://www.yii.rocks/voyti/).

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

See [docs/internals.md](docs/internals.md) for details on the testing/QA tooling.
